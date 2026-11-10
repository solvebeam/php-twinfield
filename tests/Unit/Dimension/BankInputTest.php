<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Dimension;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\Bank;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\PayAddress;
use SolveBeam\Twinfield\Dimension\WayOfPayment;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;

final class BankInputTest extends TestCase
{
    public function testWritesANewBankAccount(): void
    {
        $bank = BankInput::create()
            ->ascription('Example & Co')
            ->iban('nl91 abna 0417 1643 00')
            ->bic('abnanl2a')
            ->bankName('ABN AMRO')
            ->bankAddress('Gustav Mahlerlaan', '10')
            ->city('Amsterdam')
            ->country('NL')
            ->default();

        self::assertSame(
            '<banks><bank default="true"><ascription>Example &amp; Co</ascription><accountnumber/>'
            . '<address><field2>Gustav Mahlerlaan</field2><field3>10</field3></address><bankname>ABN AMRO</bankname>'
            . '<biccode>ABNANL2A</biccode><city>Amsterdam</city><country>NL</country><iban>NL91ABNA0417164300</iban>'
            . '<natbiccode/><postcode/><state/></bank></banks>',
            $this->xml($bank),
        );
    }

    public function testWritesTheIdOfAnExistingBankAccount(): void
    {
        self::assertStringStartsWith('<banks><bank id="3" default="false">', $this->xml(BankInput::create()->id(3)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validIbans(): iterable
    {
        yield 'NL' => ['NL91ABNA0417164300'];
        yield 'BE' => ['BE68539007547034'];
        yield 'DE' => ['DE89370400440532013000'];
        yield 'GB' => ['GB29NWBK60161331926819'];
    }

    #[DataProvider('validIbans')]
    public function testAcceptsValidIbans(string $iban): void
    {
        self::assertTrue(BankInput::isValidIban($iban));
        self::assertSame($iban, BankInput::create()->iban($iban)->iban);
    }

    public function testRefusesAnIbanWithAWrongChecksum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"NL92ABNA0417164300" is not a valid IBAN.');

        BankInput::create()->iban('NL92ABNA0417164300');
    }

    public function testRefusesSomethingThatIsNoIban(): void
    {
        self::assertFalse(BankInput::isValidIban('417164300'));
        self::assertFalse(BankInput::isValidIban('NL91-ABNA-0417'));
    }

    public function testEditKeepsAStoredIbanEvenWhenItIsInvalid(): void
    {
        $bank = new Bank(
            1,
            true,
            false,
            null,
            null,
            'NL00INVALID0000',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );

        self::assertSame('NL00INVALID0000', $bank->edit()->iban);
        self::assertSame(1, $bank->edit()->id);
    }

    public function testWritesWayOfPaymentAndPayAddressOnlyWhenSet(): void
    {
        self::assertStringNotContainsString('wayofpayment', $this->xml(BankInput::create()));

        $xml = $this->xml(BankInput::create()->wayOfPayment(WayOfPayment::Cheque, PayAddress::Beneficiary));

        self::assertStringEndsWith(
            '<state/><wayofpayment>1</wayofpayment><payaddress>3</payaddress></bank></banks>',
            $xml,
        );
    }

    public function testRefusesAPayAddressWithoutCheque(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A pay address is only for payment by cheque (WayOfPayment::Cheque).');

        BankInput::create()->wayOfPayment(WayOfPayment::Normal, PayAddress::Payer);
    }

    public function testEditKeepsWayOfPayment(): void
    {
        $bank = new Bank(
            2,
            false,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            WayOfPayment::Cheque,
            PayAddress::Payer,
        );

        self::assertSame(WayOfPayment::Cheque, $bank->edit()->wayOfPayment);
        self::assertSame(PayAddress::Payer, $bank->edit()->payAddress);
    }

    private function xml(BankInput $bank): string
    {
        $xml = XmlBuilder::create('banks');
        $bank->appendTo($xml, $xml->root());

        return $xml->toXml();
    }
}
