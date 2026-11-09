<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Supplier;

use DateTimeImmutable;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\AddressType;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Dimension\PayAddress;
use SolveBeam\Twinfield\Dimension\PaymentCondition;
use SolveBeam\Twinfield\Dimension\PostingRuleLine;
use SolveBeam\Twinfield\Dimension\RemittanceAdviceSendType;
use SolveBeam\Twinfield\Dimension\WayOfPayment;
use SolveBeam\Twinfield\Supplier\MeansOfPayment;
use SolveBeam\Twinfield\Supplier\Supplier;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Tests\Unit\Customer\CustomerTest;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class SupplierTest extends TestCase
{
    public function testReadsAllFields(): void
    {
        $supplier = Supplier::fromXml(CustomerTest::fixture('supplier-read-full'), TestTokens::organisation());

        self::assertEquals(TestTokens::company('1000'), $supplier->company);
        self::assertSame('2002', $supplier->code);
        self::assertSame('c6c05844-c075-4b51-a4ca-6f30f45d5a12', $supplier->uid);
        self::assertSame('Example Office Supplies B.V.', $supplier->name);
        self::assertSame('Office Supplies', $supplier->shortName);
        self::assertSame(DimensionStatus::Active, $supplier->status);
        self::assertTrue($supplier->inUse);
        self::assertSame(DimensionBehaviour::Normal, $supplier->behaviour);
        self::assertSame(4, $supplier->touched);
        self::assertEquals(new DateTimeImmutable('2026-03-12 10:15:30'), $supplier->modified);
        self::assertSame('www.example.com', $supplier->website);
        self::assertSame('2026/01', (string) $supplier->activeFrom);
        self::assertSame('2027/12', (string) $supplier->activeUntil);
        self::assertSame(['2REL'], $supplier->groups);
        self::assertSame(RemittanceAdviceSendType::ByEmail, $supplier->remittanceAdvice?->sendType);
        self::assertSame('accounts@example.com', $supplier->remittanceAdvice->email);
    }

    public function testReadsFinancials(): void
    {
        $financials = Supplier::fromXml(CustomerTest::fixture('supplier-read-full'), TestTokens::organisation())->financials;

        self::assertSame(14, $financials->dueDays);
        self::assertTrue($financials->payAvailable);
        self::assertSame(MeansOfPayment::PaymentFile, $financials->meansOfPayment);
        self::assertSame('SEPANLCT', $financials->payCode);
        self::assertSame('1600', $financials->balanceAccount);
        self::assertSame('CUST-4711', $financials->relationsReference);
        self::assertSame('IH', $financials->vatCode);
    }

    public function testReadsCheques(): void
    {
        $supplier = Supplier::fromXml(CustomerTest::fixture('supplier-read-full', [
            '<meansofpayment>paymentfile</meansofpayment>' => '<meansofpayment>cheques</meansofpayment>',
            '~<paycode [^>]*>SEPANLCT</paycode>~' => '<paycode name="" shortname=""/>',
        ]), TestTokens::organisation());

        self::assertSame(MeansOfPayment::Cheques, $supplier->financials->meansOfPayment);
        self::assertNull($supplier->financials->payCode);
        self::assertStringContainsString(
            '<payavailable>true</payavailable><meansofpayment>cheques</meansofpayment><paycode/>',
            $supplier->edit()->toXml('1000')->toXml(),
        );
    }

    public function testReadsAddressesAndBanks(): void
    {
        $supplier = Supplier::fromXml(CustomerTest::fixture('supplier-read-full'), TestTokens::organisation());

        self::assertCount(2, $supplier->addresses);
        self::assertSame('Oudegracht 1', $supplier->defaultAddress()?->field2);
        self::assertSame('NL001234567B01', $supplier->vatNumber());
        self::assertNull($supplier->chamberOfCommerceNumber());
        self::assertSame('Postbus 10', $supplier->addressesOfType(AddressType::Postal)[0]->field2);
        self::assertSame(2, $supplier->address(2)?->id);
        self::assertNull($supplier->address(3));

        self::assertCount(2, $supplier->banks);
        self::assertSame('NL91ABNA0417164300', $supplier->defaultBank()?->iban);
        self::assertFalse($supplier->defaultBank()->blocked);
        self::assertNull($supplier->defaultBank()->wayOfPayment);

        $foreign = $supplier->bank(2);

        self::assertNotNull($foreign);
        self::assertTrue($foreign->blocked);
        self::assertNull($foreign->iban);
        self::assertSame('401234', $foreign->nationalBankCode);
        self::assertSame('1 High Street', $foreign->bankAddress);
        self::assertSame(WayOfPayment::Cheque, $foreign->wayOfPayment);
        self::assertSame(PayAddress::Beneficiary, $foreign->payAddress);
    }

    public function testReadsPostingRulesAndPaymentConditions(): void
    {
        $supplier = Supplier::fromXml(CustomerTest::fixture('supplier-read-full'), TestTokens::organisation());

        self::assertCount(1, $supplier->postingRules);

        $rule = $supplier->postingRules[0];

        self::assertSame('EUR', $rule->currency);
        self::assertSame('121.00', (string) $rule->amount);
        self::assertEquals(
            [
                PostingRuleLine::create('4500', '80')->costCenter('100')->vatCode('IH')->description('Paper'),
                PostingRuleLine::create('4500', '20')->vatCode('IH')->description('Toner'),
            ],
            $rule->lines,
        );
        self::assertEquals([PaymentCondition::of(5, '2.75')], $supplier->paymentConditions);
    }

    public function testReadsALiveSupplier(): void
    {
        // Recorded live: Twinfield keeps an empty line in a posting rule whose lines were cleared.
        $supplier = Supplier::fromXml(CustomerTest::fixture('supplier-read'), TestTokens::organisation());

        self::assertSame('2000', $supplier->code);
        self::assertNull($supplier->shortName);
        self::assertSame(MeansOfPayment::None, $supplier->financials->meansOfPayment);
        self::assertFalse($supplier->financials->payAvailable);
        self::assertNull($supplier->financials->payCode);
        self::assertNull($supplier->financials->relationsReference);
        self::assertNull($supplier->financials->vatCode);
        self::assertSame(RemittanceAdviceSendType::ToFileManager, $supplier->remittanceAdvice?->sendType);
        self::assertNull($supplier->remittanceAdvice->email);
        self::assertSame('12345678,', $supplier->chamberOfCommerceNumber());
        self::assertFalse($supplier->defaultBank()?->blocked);
        self::assertSame([], $supplier->groups);
        self::assertSame([], $supplier->paymentConditions);
        self::assertCount(1, $supplier->postingRules);
        self::assertSame([], $supplier->postingRules[0]->lines);
    }

    public function testReadsLivePostingRuleLinesWithoutRatio(): void
    {
        $supplier = Supplier::fromXml(CustomerTest::fixture('supplier-read-posting-rules'), TestTokens::organisation());

        self::assertSame([], $supplier->banks);
        self::assertEquals(
            [
                PostingRuleLine::create('3030', null)->vatCode('IL')->description('Invoice [Invoice number]'),
                PostingRuleLine::create('3031', null)->vatCode('#')->description('Invoice [Invoice number]'),
            ],
            $supplier->postingRules[0]->lines,
        );
    }

    public function testEditSendsWhatTwinfieldReturned(): void
    {
        $xml = Supplier::fromXml(CustomerTest::fixture('supplier-read-full'), TestTokens::organisation())->edit()->toXml('1000')->toXml();

        self::assertSame(self::pretty(<<<'XML'
            <dimension>
              <office>1000</office>
              <type>CRD</type>
              <code>2002</code>
              <name>Example Office Supplies B.V.</name>
              <shortname>Office Supplies</shortname>
              <beginperiod>1</beginperiod>
              <beginyear>2026</beginyear>
              <endperiod>12</endperiod>
              <endyear>2027</endyear>
              <website>www.example.com</website>
              <financials>
                <matchtype>customersupplier</matchtype>
                <accounttype>inherit</accounttype>
                <subanalyse>false</subanalyse>
                <substitutionlevel>1</substitutionlevel>
                <duedays>14</duedays>
                <payavailable>true</payavailable>
                <meansofpayment>paymentfile</meansofpayment>
                <paycode>SEPANLCT</paycode>
                <substitutewith>1600</substitutewith>
                <relationsreference>CUST-4711</relationsreference>
                <vatcode>IH</vatcode>
              </financials>
              <remittanceadvice>
                <sendtype>ByEmail</sendtype>
                <sendmail>accounts@example.com</sendmail>
              </remittanceadvice>
              <addresses>
                <address id="1" type="invoice" default="true">
                  <name>Example Office Supplies B.V.</name>
                  <country>NL</country>
                  <city>Utrecht</city>
                  <postcode>3511 AA</postcode>
                  <telephone/>
                  <telefax/>
                  <email>invoices@example.com</email>
                  <field1/>
                  <field2>Oudegracht 1</field2>
                  <field3/>
                  <field4>NL001234567B01</field4>
                  <field5/>
                  <field6/>
                </address>
                <address id="2" type="postal" default="false">
                  <name>Example Office Supplies B.V.</name>
                  <country>NL</country>
                  <city>Utrecht</city>
                  <postcode>3500 AA</postcode>
                  <telephone/>
                  <telefax/>
                  <email/>
                  <field1/>
                  <field2>Postbus 10</field2>
                  <field3/>
                  <field4/>
                  <field5/>
                  <field6/>
                </address>
              </addresses>
              <banks>
                <bank id="1" default="true">
                  <ascription>Example Office Supplies B.V.</ascription>
                  <accountnumber>417164300</accountnumber>
                  <address>
                    <field2/>
                    <field3/>
                  </address>
                  <bankname>ABN AMRO</bankname>
                  <biccode>ABNANL2A</biccode>
                  <city>Amsterdam</city>
                  <country>NL</country>
                  <iban>NL91ABNA0417164300</iban>
                  <natbiccode/>
                  <postcode/>
                  <state/>
                </bank>
                <bank id="2" default="false">
                  <ascription>Example Office Supplies Ltd</ascription>
                  <accountnumber>12345678</accountnumber>
                  <address>
                    <field2>1 High Street</field2>
                    <field3>1</field3>
                  </address>
                  <bankname>Example Bank</bankname>
                  <biccode>EXAMGB2L</biccode>
                  <city>London</city>
                  <country>GB</country>
                  <iban/>
                  <natbiccode>401234</natbiccode>
                  <postcode>EC1A 1AA</postcode>
                  <state/>
                  <wayofpayment>1</wayofpayment>
                  <payaddress>3</payaddress>
                </bank>
              </banks>
              <groups>
                <group>2REL</group>
              </groups>
              <postingrules>
                <postingrule id="1" status="active">
                  <currency>EUR</currency>
                  <amount>121.00</amount>
                  <description>Office supplies</description>
                  <lines>
                    <line>
                      <office/>
                      <dimension1>4500</dimension1>
                      <dimension2>100</dimension2>
                      <dimension3/>
                      <ratio>80</ratio>
                      <vatcode>IH</vatcode>
                      <description>Paper</description>
                    </line>
                    <line>
                      <office/>
                      <dimension1>4500</dimension1>
                      <dimension2/>
                      <dimension3/>
                      <ratio>20</ratio>
                      <vatcode>IH</vatcode>
                      <description>Toner</description>
                    </line>
                  </lines>
                </postingrule>
              </postingrules>
              <paymentconditions>
                <paymentcondition>
                  <discountdays>5</discountdays>
                  <discountpercentage>2.75</discountpercentage>
                </paymentcondition>
              </paymentconditions>
            </dimension>
            XML), self::pretty($xml));
    }

    public function testEditOfALiveSupplierLeavesOutWhatTwinfieldLeftOut(): void
    {
        $xml = Supplier::fromXml(CustomerTest::fixture('supplier-read-posting-rules'), TestTokens::organisation())->edit()->toXml('1000')->toXml();

        self::assertStringNotContainsString('<groups', $xml);
        self::assertStringNotContainsString('<paymentconditions', $xml);
        self::assertStringNotContainsString('<collectmandate>', $xml);
        self::assertStringNotContainsString('<ebilling>', $xml);
        self::assertStringContainsString('<banks/>', $xml);
        self::assertStringContainsString('<ratio/><vatcode>#</vatcode>', $xml);
        self::assertStringContainsString('<payavailable>false</payavailable><meansofpayment>none</meansofpayment><paycode/>', $xml);
        self::assertStringContainsString('<remittanceadvice><sendtype>ToFileManager</sendtype><sendmail/></remittanceadvice>', $xml);
    }

    public function testThrowsOnAnotherDimensionType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/type is "DEB", expected CRD.');

        Supplier::fromXml(CustomerTest::fixture('supplier-read', ['<type name="Crediteuren" shortname="Crediteuren">CRD</type>' => '<type>DEB</type>']), TestTokens::organisation());
    }

    public function testThrowsOnAnUnknownMeansOfPayment(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/financials/meansofpayment is "creditcard", expected one of none, paymentfile, cheques.');

        Supplier::fromXml(CustomerTest::fixture('supplier-read', ['<meansofpayment>none</meansofpayment>' => '<meansofpayment>creditcard</meansofpayment>']), TestTokens::organisation());
    }

    public function testThrowsOnAMissingRelationsReference(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/financials/relationsreference is missing.');

        Supplier::fromXml(CustomerTest::fixture('supplier-read', ['<relationsreference/>' => '']), TestTokens::organisation());
    }

    public function testThrowsOnAnUnknownWayOfPayment(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/banks/bank[2]/wayofpayment is "9", expected one of 0, 1, 2.');

        Supplier::fromXml(CustomerTest::fixture('supplier-read-full', ['<wayofpayment>1</wayofpayment>' => '<wayofpayment>9</wayofpayment>']), TestTokens::organisation());
    }

    private static function pretty(string $xml): string
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;
        $document->loadXML($xml);

        return (string) $document->saveXML($document->documentElement);
    }
}
