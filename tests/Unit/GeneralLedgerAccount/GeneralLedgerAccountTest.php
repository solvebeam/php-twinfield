<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\GeneralLedgerAccount;

use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\GeneralLedgerAccount\ChildValidation;
use SolveBeam\Twinfield\GeneralLedgerAccount\ChildValidationType;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccount;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountType;
use SolveBeam\Twinfield\GeneralLedgerAccount\SubAnalysis;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\VatCode\VatCodeReference;
use SolveBeam\Twinfield\VatCode\VatCodeType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class GeneralLedgerAccountTest extends TestCase
{
    public function testReadsABalanceSheetAccount(): void
    {
        $account = self::account('general-ledger-account-read-balance-sheet');

        self::assertEquals(TestTokens::company('1000'), $account->company);
        self::assertSame(GeneralLedgerAccountType::BalanceSheet, $account->type);
        self::assertTrue($account->isBalanceSheet());
        self::assertFalse($account->isProfitAndLoss());
        self::assertSame('0200', $account->code);
        self::assertSame('dffe0708-3b77-40b6-ad1a-41cbbf4d77d6', $account->uid);
        self::assertSame('Inventaris', $account->name);
        self::assertNull($account->shortName);
        self::assertSame(DimensionStatus::Active, $account->status);
        self::assertFalse($account->inUse);
        self::assertSame(DimensionBehaviour::Normal, $account->behaviour);
        self::assertSame(0, $account->touched);
        self::assertSame('2016-04-16 06:40:54', $account->modified?->format('Y-m-d H:i:s'));
        self::assertNull($account->activeFrom);
        self::assertNull($account->activeUntil);
        self::assertFalse($account->matchable);
        self::assertSame(SubAnalysis::Required, $account->subAnalysis);
        self::assertNull($account->vatCode);
        self::assertFalse($account->vatCodeFixed);
        self::assertEquals([
            new ChildValidation(ChildValidationType::Type, 2, 'KPL'),
            new ChildValidation(ChildValidationType::Type, 3, 'AST'),
        ], $account->childValidations);
        self::assertSame([], $account->groups);
    }

    public function testReadsAProfitAndLossAccount(): void
    {
        $account = self::account('general-ledger-account-read-profit-and-loss');

        self::assertSame(GeneralLedgerAccountType::ProfitAndLoss, $account->type);
        self::assertTrue($account->isProfitAndLoss());
        self::assertSame(SubAnalysis::Optional, $account->subAnalysis);
        self::assertTrue($account->inUse);
        self::assertSame(1, $account->touched);
        self::assertEquals([ChildValidation::dimensionType('KPL')], $account->childValidations);
    }

    public function testReadsAHiddenAccountWithoutChildValidations(): void
    {
        $account = self::account('general-ledger-account-read-hidden');

        self::assertSame(DimensionStatus::Hidden, $account->status);
        self::assertSame(SubAnalysis::NotAllowed, $account->subAnalysis);
        self::assertSame([], $account->childValidations);
    }

    public function testReadsAllSettings(): void
    {
        $account = self::account('general-ledger-account-read-full');

        self::assertSame('Diensten', $account->shortName);
        self::assertEquals(Period::of(2026, 1), $account->activeFrom);
        self::assertEquals(Period::of(2027, 12), $account->activeUntil);
        self::assertTrue($account->matchable);
        self::assertEquals(new VatCodeReference('VH', 'BTW 21%', 'VH', VatCodeType::Sales), $account->vatCode);
        self::assertTrue($account->vatCodeFixed);
        self::assertEquals([
            ChildValidation::dimensionType('KPL'),
            ChildValidation::dimension('P0001', 3),
            ChildValidation::group('REVENUE'),
        ], $account->childValidations);
        self::assertSame(['REVENUE', 'SERVICES'], $account->groups);
        self::assertTrue($account->inGroup('SERVICES'));
        self::assertFalse($account->inGroup('OTHER'));
    }

    public function testReadsTheSaveResponseOfTheDocumentation(): void
    {
        $account = self::account('general-ledger-account-save');

        self::assertSame('8010', $account->code);
        self::assertNull($account->modified);
        self::assertNull($account->vatCode, 'An empty <vatcode> with empty attributes is no VAT code.');
        self::assertSame(['REVENUE'], $account->groups);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidResponses(): iterable
    {
        yield 'another dimension type' => [
            'general-ledger-account-read-wrong-type',
            'dimension/type is "KPL", expected one of BAS, PNL.',
        ];

        yield 'account type of the other type' => [
            'general-ledger-account-read-account-type-mismatch',
            'dimension/financials/accounttype is "balance", expected profitandloss for a profit and loss account.',
        ];

        yield 'unknown sub-analysis' => [
            'general-ledger-account-read-unknown-subanalyse',
            'dimension/financials/subanalyse is "sometimes", expected one of true, false, maybe.',
        ];
    }

    #[DataProvider('invalidResponses')]
    public function testThrowsOnAnUnexpectedResponse(string $fixture, string $message): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($message);

        self::account($fixture);
    }

    public function testThrowsOnAVatCodeWithoutType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('vatcode/@type is "", expected one of purchase, sales.');

        VatCodeReference::fromXml(
            self::element('<vatcode name="BTW 21%" shortname="VH" type="" fixed="false">VH</vatcode>'),
        );
    }

    public function testThrowsOnAnUnknownChildValidationType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('childvalidation/@type is "article", expected one of code, type, group.');

        ChildValidation::fromXml(self::element('<childvalidation type="article" level="2">KPL</childvalidation>'));
    }

    public function testRefusesAFixedVatCodeWithoutVatCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A general ledger account without VAT code cannot have a fixed VAT code.');

        self::account('general-ledger-account-read-full', ['fixed="true">VH<' => 'fixed="true"><']);
    }

    /**
     * edit() gives every value, so saving it unchanged sends what Twinfield has.
     */
    public function testEditCarriesEveryValue(): void
    {
        $input = self::account('general-ledger-account-read-full')->edit();

        self::assertSame(GeneralLedgerAccountType::ProfitAndLoss, $input->type);
        self::assertSame(
            '<dimension><office>1000</office><type>PNL</type><code>8010</code><name>Omzet diensten</name>'
                . '<shortname>Diensten</shortname><beginperiod>1</beginperiod><beginyear>2026</beginyear>'
                . '<endperiod>12</endperiod><endyear>2027</endyear>'
                . '<financials><matchtype>matchable</matchtype><subanalyse>true</subanalyse>'
                . '<vatcode fixed="true">VH</vatcode><childvalidations>'
                . '<childvalidation type="type" level="2">KPL</childvalidation>'
                . '<childvalidation type="code" level="3">P0001</childvalidation>'
                . '<childvalidation type="group" level="0">REVENUE</childvalidation>'
                . '</childvalidations></financials>'
                . '<groups><group>REVENUE</group><group>SERVICES</group></groups></dimension>',
            $input->toXml('1000')->toXml(),
        );
    }

    public function testEditLeavesOutEmptyListsAndClearsEmptyValues(): void
    {
        $input = self::account('general-ledger-account-read-hidden')->edit();

        self::assertSame(
            '<dimension><office>1000</office><type>BAS</type><code>0100</code><name>Oprichtingskosten</name>'
                . '<shortname/><beginperiod>0</beginperiod><beginyear>0</beginyear>'
                . '<endperiod>0</endperiod><endyear>0</endyear>'
                . '<financials><matchtype>notmatchable</matchtype><subanalyse>false</subanalyse>'
                . '<vatcode fixed="false"/></financials></dimension>',
            $input->toXml('1000')->toXml(),
        );
    }

    /**
     * @param array<string, string> $replace Changes to the response XML, e.g. to make it invalid.
     */
    private static function account(string $fixture, array $replace = []): GeneralLedgerAccount
    {
        $soap = new DOMDocument();
        $soap->load(dirname(__DIR__, 2) . '/Fixtures/soap/' . $fixture . '.xml');

        $xml = strtr((string) $soap->getElementsByTagName('ProcessXmlStringResult')->item(0)?->textContent, $replace);

        return GeneralLedgerAccount::fromXml(
            XmlElement::root(ProcessXmlResult::fromXml($xml)),
            TestTokens::organisation(),
        );
    }

    private static function element(string $xml): XmlElement
    {
        $document = new DOMDocument();
        $document->loadXML($xml);

        self::assertNotNull($document->documentElement);

        return new XmlElement($document->documentElement, (string) $document->documentElement->localName);
    }
}
