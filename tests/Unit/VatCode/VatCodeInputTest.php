<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\VatCode;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Soap\SoapResponse;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\VatCode\Percentage;
use SolveBeam\Twinfield\VatCode\VatAccountInput;
use SolveBeam\Twinfield\VatCode\VatAccountLineType;
use SolveBeam\Twinfield\VatCode\VatCode;
use SolveBeam\Twinfield\VatCode\VatCodeInput;
use SolveBeam\Twinfield\VatCode\VatCodeType;
use SolveBeam\Twinfield\VatCode\VatPercentageInput;

final class VatCodeInputTest extends TestCase
{
    public function testBuildsTheDocumentedExample(): void
    {
        $input = VatCodeInput::sales('VH', 'BTW 21%')
            ->shortName('VH 21%')
            ->percentage(
                VatPercentageInput::from(new DateTimeImmutable('2012-10-01'), '21')
                    ->name('BTW 21%')
                    ->shortName('VH 21%')
                    ->account(VatAccountInput::vat('1530')->group('NL', 'NL1A')),
            );

        self::assertSame(
            '<vat><office>001</office><code>VH</code><name>BTW 21%</name><shortname>VH 21%</shortname>'
            . '<type>sales</type><percentages><percentage><date>20121001</date><percentage>21</percentage>'
            . '<name>BTW 21%</name><shortname>VH 21%</shortname><accounts><account><dim1>1530</dim1>'
            . '<groupcountry>NL</groupcountry><group>NL1A</group><percentage>100</percentage>'
            . '<linetype>vat</linetype></account></accounts></percentage></percentages></vat>',
            $input->toXml('001')->toXml(),
        );
    }

    public function testIsImmutable(): void
    {
        $input = VatCodeInput::purchase('IH', 'BTW 21%');
        $changed = $input->name('Voorbelasting 21%')->shortName('I 21%')->type(VatCodeType::Sales);

        self::assertSame('BTW 21%', $input->name);
        self::assertNull($input->shortName);
        self::assertSame(VatCodeType::Purchase, $input->type);
        self::assertSame('Voorbelasting 21%', $changed->name);
        self::assertSame('I 21%', $changed->shortName);
        self::assertSame(VatCodeType::Sales, $changed->type);
        self::assertNull($changed->shortName('')->shortName);

        $account = VatAccountInput::vat('1530');
        $grouped = $account->group('NL', 'NL1A');
        self::assertNull($account->group);
        self::assertSame('NL1A', $grouped->group);
        self::assertNull($grouped->withoutGroup()->groupCountry);
    }

    public function testPercentageNameDefaultsToTheNameOfTheVatCode(): void
    {
        $xml = VatCodeInput::sales('VH', 'BTW 21%')
            ->percentage(VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21')->account(VatAccountInput::vat('1530')))
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString('<percentage>21</percentage><name>BTW 21%</name><shortname/>', $xml);
        self::assertStringContainsString('<account><dim1>1530</dim1><percentage>100</percentage>', $xml, 'No VAT group: not sent.');
    }

    public function testKeepsPercentagesSortedAndReplacesTheSameDate(): void
    {
        $input = VatCodeInput::sales('VL', 'BTW laag')
            ->percentage(VatPercentageInput::from(new DateTimeImmutable('2019-01-01'), '9'))
            ->percentage(VatPercentageInput::from(new DateTimeImmutable('2000-01-01'), '6'))
            ->percentage(VatPercentageInput::from(new DateTimeImmutable('2019-01-01 13:00'), '8'));

        self::assertSame(['6', '8'], array_map(
            static fn (VatPercentageInput $line): string => $line->percentage->value,
            $input->percentages(),
        ));
        self::assertSame('8', $input->percentageFrom(new DateTimeImmutable('2019-01-01'))?->percentage->value);
        self::assertNull($input->percentageFrom(new DateTimeImmutable('2019-01-02')));
    }

    public function testEditRoundTripsTheReadResponse(): void
    {
        $vatCode = $this->vatCode('vat-read-reverse-charge');

        self::assertSame(
            '<vat><office>1000</office><code>ICV-H</code><name>IC verwervingen binnnen EU</name>'
            . '<shortname>ICV Hoog</shortname><type>sales</type><percentages>'
            . '<percentage><date>20100101</date><percentage>19</percentage><name>IC verwervingen binnnen EU</name>'
            . '<shortname>ICV 19%</shortname><accounts>'
            . '<account><dim1>16626</dim1><groupcountry>NL</groupcountry><group>NL4B</group><percentage>-100</percentage><linetype>vat</linetype></account>'
            . '<account><dim1>16627</dim1><groupcountry>NL</groupcountry><group>NL5B</group><percentage>100</percentage><linetype>vat</linetype></account>'
            . '</accounts></percentage>'
            . '<percentage><date>20121001</date><percentage>21</percentage><name>IC verwervingen binnnen EU</name>'
            . '<shortname>ICV 21%</shortname><accounts>'
            . '<account><dim1>16626</dim1><groupcountry>NL</groupcountry><group>NL4B</group><percentage>-100</percentage><linetype>vat</linetype></account>'
            . '<account><dim1>16627</dim1><groupcountry>NL</groupcountry><group>NL5B</group><percentage>100</percentage><linetype>vat</linetype></account>'
            . '</accounts></percentage></percentages></vat>',
            $vatCode->edit()->toXml('1000')->toXml(),
        );
    }

    public function testEditKeepsAnEmptyShortNameAndVatGroup(): void
    {
        $xml = $this->vatCode('vat-read-without-group')->edit()->toXml('1000')->toXml();

        self::assertStringContainsString('<name>Geen BTW</name><shortname/><type>purchase</type>', $xml);
        self::assertStringContainsString('<account><dim1>16619</dim1><percentage>100</percentage><linetype>vat</linetype>', $xml);
    }

    public function testNewPercentageFromCopiesTheAccountsOfThePreviousPercentage(): void
    {
        $input = $this->vatCode('vat-read-reverse-charge')
            ->edit()
            ->newPercentageFrom(new DateTimeImmutable('2027-01-01'), '22', 'ICV 22%');

        $lines = $input->percentages();
        self::assertCount(3, $lines);

        $new = $lines[2];
        self::assertEquals(new DateTimeImmutable('2027-01-01'), $new->from);
        self::assertSame('22', $new->percentage->value);
        self::assertSame('ICV 22%', $new->name);
        self::assertSame('ICV 21%', $new->shortName, 'Kept from the previous percentage, as it was not given.');
        self::assertEquals($lines[1]->accounts, $new->accounts);
        self::assertSame('21', $lines[1]->percentage->value, 'The previous percentage is unchanged.');
    }

    public function testNewPercentageFromBetweenTwoPercentagesCopiesTheEarlierOne(): void
    {
        $input = $this->vatCode('vat-read')->edit()->newPercentageFrom(new DateTimeImmutable('2005-01-01'), '20');

        self::assertSame('BTW 19%', $input->percentageFrom(new DateTimeImmutable('2005-01-01'))?->name);
    }

    public function testNewPercentageFromNeedsAnEarlierPercentage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VAT code VH has no percentage before 2000-01-01 to copy the accounts from: use percentage().');

        $this->vatCode('vat-read')->edit()->newPercentageFrom(new DateTimeImmutable('2000-01-01'), '20');
    }

    public function testDetailLine(): void
    {
        $account = VatAccountInput::detail('4300', '30');

        self::assertSame(VatAccountLineType::Detail, $account->lineType);
        self::assertSame('30', $account->percentage->value);
        self::assertSame('50', $account->percentage('50')->percentage->value);
    }

    public function testRefusesAnInputWithoutPercentages(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('VAT code VH needs at least one percentage: add one with percentage().');

        VatCodeInput::sales('VH', 'BTW 21%')->toXml('1000');
    }

    public function testRefusesAPercentageWithoutAccounts(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('VAT code VH: the percentage from 2026-01-01 needs at least one account');

        VatCodeInput::sales('VH', 'BTW 21%')
            ->percentage(VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21'))
            ->toXml('1000');
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'invalid code' => [static fn (): mixed => VatCodeInput::sales('vh', 'BTW'), '"vh" is not a code'];
        yield 'empty name' => [static fn (): mixed => VatCodeInput::sales('VH', ''), 'VAT code name must not be empty.'];
        yield 'long name' => [
            static fn (): mixed => VatCodeInput::sales('VH', str_repeat('x', 41)),
            'VAT code name must be at most 40 characters',
        ];
        yield 'long short name' => [
            static fn (): mixed => VatCodeInput::sales('VH', 'BTW')->shortName(str_repeat('x', 21)),
            'VAT code short name must be at most 20 characters',
        ];
        yield 'negative rate' => [
            static fn (): mixed => VatPercentageInput::from(new DateTimeImmutable(), '-1'),
            'A VAT percentage must be 0 to 100, -1 given.',
        ];
        yield 'rate above 100' => [
            static fn (): mixed => VatPercentageInput::from(new DateTimeImmutable(), '100.5'),
            'A VAT percentage must be 0 to 100, 100.5 given.',
        ];
        yield 'rate with comma' => [
            static fn (): mixed => VatPercentageInput::from(new DateTimeImmutable(), Percentage::fromString('5,5')),
            '"5,5" is not a percentage',
        ];
        yield 'long percentage name' => [
            static fn (): mixed => VatPercentageInput::from(new DateTimeImmutable(), '21')->name(str_repeat('x', 41)),
            'VAT percentage name must be at most 40 characters',
        ];
        yield 'long percentage short name' => [
            static fn (): mixed => VatPercentageInput::from(new DateTimeImmutable(), '21')->shortName(str_repeat('x', 21)),
            'VAT percentage short name must be at most 20 characters',
        ];
        yield 'invalid account' => [static fn (): mixed => VatAccountInput::vat('15 30'), '"15 30" is not a code'];
        yield 'share above 100' => [
            static fn (): mixed => VatAccountInput::vat('1530', '101'),
            'The share of a VAT account must be -100 to 100 percent, 101 given.',
        ];
        yield 'share below -100' => [
            static fn (): mixed => VatAccountInput::vat('1530')->percentage('-100.1'),
            'The share of a VAT account must be -100 to 100 percent, -100.1 given.',
        ];
        yield 'invalid VAT group' => [
            static fn (): mixed => VatAccountInput::vat('1530')->group('NL', 'nl 1a'),
            '"nl 1a" is not a code',
        ];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('invalidInput')]
    public function testRefusesInvalidInput(Closure $build, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $build();
    }

    private function vatCode(string $fixture): VatCode
    {
        $result = ProcessXmlResult::fromSoapResponse(
            SoapResponse::fromHttpResponse(ReplayClient::response($fixture)),
        );

        return VatCode::fromXml(XmlElement::root($result), '1000');
    }
}
