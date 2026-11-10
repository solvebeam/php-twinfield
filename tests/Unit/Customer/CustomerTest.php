<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Customer;

use DOMDocument;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Customer\CollectionSchema;
use SolveBeam\Twinfield\Customer\Customer;
use SolveBeam\Twinfield\Customer\MeansOfPayment;
use SolveBeam\Twinfield\Customer\SendReminder;
use SolveBeam\Twinfield\Dimension\AddressType;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Dimension\PaymentCondition;
use SolveBeam\Twinfield\Dimension\PostingRuleLine;
use SolveBeam\Twinfield\Dimension\RemittanceAdviceSendType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class CustomerTest extends TestCase
{
    public function testReadsAllFields(): void
    {
        $customer = Customer::fromXml(self::fixture('customer-read'), TestTokens::organisation());

        self::assertEquals(TestTokens::company('1000'), $customer->company);
        self::assertSame('1000', $customer->code);
        self::assertSame('3f1c9a2e-5b7d-4c8e-9f0a-1b2c3d4e5f60', $customer->uid);
        self::assertSame('Example Trading & Co B.V.', $customer->name);
        self::assertSame('Example', $customer->shortName);
        self::assertSame(DimensionStatus::Active, $customer->status);
        self::assertTrue($customer->inUse);
        self::assertSame(DimensionBehaviour::Normal, $customer->behaviour);
        self::assertSame(3, $customer->touched);
        self::assertEquals(new DateTimeImmutable('2026-03-14 09:30:15'), $customer->modified);
        self::assertSame('www.example.com', $customer->website);
        self::assertSame('2026/01', (string) $customer->activeFrom);
        self::assertNull($customer->activeUntil);
        self::assertSame('DISCOUNT', $customer->discountArticle);
        self::assertSame(['RETAIL'], $customer->groups);
    }

    public function testReadsFinancials(): void
    {
        $financials = Customer::fromXml(self::fixture('customer-read'), TestTokens::organisation())->financials;

        self::assertSame(30, $financials->dueDays);
        self::assertTrue($financials->directDebit);
        self::assertSame(MeansOfPayment::PaymentFile, $financials->meansOfPayment);
        self::assertSame('SEPANLDD', $financials->payCode);
        self::assertTrue($financials->eInvoicing);
        self::assertSame('invoices@example.com', $financials->eInvoicingEmail);
        self::assertSame('1300', $financials->balanceAccount);
        self::assertSame('VH', $financials->vatCode);
        self::assertSame('MANDATE-001', $financials->collectMandate?->id);
        self::assertEquals(new DateTimeImmutable('2026-01-15'), $financials->collectMandate->signatureDate);
        self::assertSame(CollectionSchema::B2b, $financials->collectionSchema);
    }

    public function testReadsCreditManagementAndRemittanceAdvice(): void
    {
        $customer = Customer::fromXml(self::fixture('customer-read'), TestTokens::organisation());
        $credit = $customer->creditManagement;

        self::assertSame('JANSEN', $credit->responsibleUser);
        self::assertSame('5000.00', $credit->creditLimit->value);
        self::assertSame(SendReminder::ByEmail, $credit->sendReminder);
        self::assertSame('finance@example.com', $credit->reminderEmail);
        self::assertFalse($credit->blocked);
        self::assertTrue($credit->rightOfUse);
        self::assertSame('RETAIL', $credit->segmentCode);
        self::assertSame('Pays on time.', $credit->comment);

        self::assertSame(RemittanceAdviceSendType::ByEmail, $customer->remittanceAdvice?->sendType);
        self::assertSame('remittance@example.com', $customer->remittanceAdvice->email);
    }

    public function testReadsAddressesAndBanks(): void
    {
        $customer = Customer::fromXml(self::fixture('customer-read'), TestTokens::organisation());

        self::assertCount(2, $customer->addresses);

        $invoice = $customer->defaultAddress();

        self::assertNotNull($invoice);
        self::assertSame(1, $invoice->id);
        self::assertSame(AddressType::Invoice, $invoice->type);
        self::assertSame('Example Trading & Co B.V.', $invoice->name);
        self::assertSame('NL', $invoice->country);
        self::assertSame('Amsterdam', $invoice->city);
        self::assertSame('1012 AB', $invoice->postcode);
        self::assertSame('020-1234567', $invoice->telephone);
        self::assertNull($invoice->telefax);
        self::assertSame('info@example.com', $invoice->email);
        self::assertSame('T.a.v. J. Jansen', $invoice->field1);
        self::assertSame('Damrak 1', $invoice->field2);
        self::assertSame('NL001234567B01', $customer->vatNumber());
        self::assertSame('12345678', $customer->chamberOfCommerceNumber());

        self::assertSame('Postbus 123', $customer->address(2)?->field2);
        self::assertSame(
            [2],
            array_map(static fn ($address) => $address->id, $customer->addressesOfType(AddressType::Postal)),
        );
        self::assertNull($customer->address(3));

        $bank = $customer->defaultBank();

        self::assertNotNull($bank);
        self::assertSame($bank, $customer->bank(1));
        self::assertSame('Example Trading & Co B.V.', $bank->ascription);
        self::assertSame('417164300', $bank->accountNumber);
        self::assertSame('NL91ABNA0417164300', $bank->iban);
        self::assertSame('ABNANL2A', $bank->bic);
        self::assertSame('ABN AMRO', $bank->bankName);
        self::assertSame('Gustav Mahlerlaan', $bank->bankAddress);
        self::assertSame('10', $bank->bankAddressNumber);
        self::assertNull($bank->nationalBankCode);
    }

    public function testReadsPostingRulesAndPaymentConditions(): void
    {
        $customer = Customer::fromXml(self::fixture('customer-read'), TestTokens::organisation());

        self::assertCount(1, $customer->postingRules);

        $rule = $customer->postingRules[0];

        self::assertSame(1, $rule->id);
        self::assertSame('EUR', $rule->currency);
        self::assertSame('125.00', $rule->amount?->value);
        self::assertSame('Monthly fee', $rule->description);
        self::assertEquals([
            PostingRuleLine::create('8000', '78.4')->costCenter('100')->vatCode('VH')->description('Subscription'),
            PostingRuleLine::create('8010', '21.6')->vatCode('VH'),
        ], $rule->lines);

        self::assertEquals([PaymentCondition::of(8, '2.5')], $customer->paymentConditions);
    }

    public function testReadsAMinimalCustomer(): void
    {
        $customer = Customer::fromXml(self::fixture('customer-read-minimal'), TestTokens::organisation());

        self::assertNull($customer->shortName);
        self::assertNull($customer->website);
        self::assertNull($customer->activeFrom);
        self::assertNull($customer->financials->payCode);
        self::assertNull($customer->financials->vatCode);
        self::assertNull($customer->financials->collectMandate);
        self::assertNull($customer->financials->collectionSchema);
        self::assertNull($customer->creditManagement->responsibleUser);
        self::assertNull($customer->creditManagement->rightOfUse);
        self::assertSame('0.00', $customer->creditManagement->creditLimit->value);
        self::assertNull($customer->discountArticle);
        self::assertSame([], $customer->banks);
        self::assertNull($customer->defaultBank());
        self::assertSame([], $customer->groups);
        self::assertSame([], $customer->paymentConditions);

        // Twinfield can return an address without name, and a posting rule without amount.
        self::assertNull($customer->defaultAddress()?->name);
        self::assertNull($customer->postingRules[0]->currency);
        self::assertNull($customer->postingRules[0]->amount);
        self::assertSame([], $customer->postingRules[0]->lines);
    }

    public function testEditSendsWhatTwinfieldReturned(): void
    {
        $xml = Customer::fromXml(self::fixture('customer-read'), TestTokens::organisation())
            ->edit()
            ->toXml('1000')
            ->toXml();

        self::assertSame(self::pretty(<<<'XML'
            <dimension>
              <office>1000</office>
              <type>DEB</type>
              <code>1000</code>
              <name>Example Trading &amp; Co B.V.</name>
              <shortname>Example</shortname>
              <beginperiod>1</beginperiod>
              <beginyear>2026</beginyear>
              <endperiod>0</endperiod>
              <endyear>0</endyear>
              <website>www.example.com</website>
              <financials>
                <matchtype>customersupplier</matchtype>
                <accounttype>inherit</accounttype>
                <subanalyse>false</subanalyse>
                <substitutionlevel>1</substitutionlevel>
                <duedays>30</duedays>
                <payavailable>true</payavailable>
                <meansofpayment>paymentfile</meansofpayment>
                <paycode>SEPANLDD</paycode>
                <ebilling>true</ebilling>
                <ebillmail>invoices@example.com</ebillmail>
                <substitutewith>1300</substitutewith>
                <vatcode>VH</vatcode>
                <collectmandate>
                  <id>MANDATE-001</id>
                  <signaturedate>20260115</signaturedate>
                </collectmandate>
                <collectionschema>b2b</collectionschema>
              </financials>
              <creditmanagement>
                <responsibleuser>JANSEN</responsibleuser>
                <basecreditlimit>5000.00</basecreditlimit>
                <sendreminder>email</sendreminder>
                <reminderemail>finance@example.com</reminderemail>
                <blocked>false</blocked>
                <freetext1>true</freetext1>
                <freetext2>RETAIL</freetext2>
                <comment>Pays on time.</comment>
              </creditmanagement>
              <remittanceadvice>
                <sendtype>ByEmail</sendtype>
                <sendmail>remittance@example.com</sendmail>
              </remittanceadvice>
              <invoicing>
                <discountarticle>DISCOUNT</discountarticle>
              </invoicing>
              <addresses>
                <address id="1" type="invoice" default="true">
                  <name>Example Trading &amp; Co B.V.</name>
                  <country>NL</country>
                  <city>Amsterdam</city>
                  <postcode>1012 AB</postcode>
                  <telephone>020-1234567</telephone>
                  <telefax/>
                  <email>info@example.com</email>
                  <field1>T.a.v. J. Jansen</field1>
                  <field2>Damrak 1</field2>
                  <field3/>
                  <field4>NL001234567B01</field4>
                  <field5>12345678</field5>
                  <field6/>
                </address>
                <address id="2" type="postal" default="false">
                  <name>Example Trading &amp; Co B.V.</name>
                  <country>NL</country>
                  <city>Amsterdam</city>
                  <postcode>1000 AA</postcode>
                  <telephone/>
                  <telefax/>
                  <email/>
                  <field1/>
                  <field2>Postbus 123</field2>
                  <field3/>
                  <field4/>
                  <field5/>
                  <field6/>
                </address>
              </addresses>
              <banks>
                <bank id="1" default="true">
                  <ascription>Example Trading &amp; Co B.V.</ascription>
                  <accountnumber>417164300</accountnumber>
                  <address>
                    <field2>Gustav Mahlerlaan</field2>
                    <field3>10</field3>
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
              </banks>
              <groups>
                <group>RETAIL</group>
              </groups>
              <postingrules>
                <postingrule id="1" status="active">
                  <currency>EUR</currency>
                  <amount>125.00</amount>
                  <description>Monthly fee</description>
                  <lines>
                    <line>
                      <office/>
                      <dimension1>8000</dimension1>
                      <dimension2>100</dimension2>
                      <dimension3/>
                      <ratio>78.4</ratio>
                      <vatcode>VH</vatcode>
                      <description>Subscription</description>
                    </line>
                    <line>
                      <office/>
                      <dimension1>8010</dimension1>
                      <dimension2/>
                      <dimension3/>
                      <ratio>21.6</ratio>
                      <vatcode>VH</vatcode>
                      <description/>
                    </line>
                  </lines>
                </postingrule>
              </postingrules>
              <paymentconditions>
                <paymentcondition>
                  <discountdays>8</discountdays>
                  <discountpercentage>2.5</discountpercentage>
                </paymentcondition>
              </paymentconditions>
            </dimension>
            XML), self::pretty($xml));
    }

    public function testEditOfAMinimalCustomerLeavesOutWhatTwinfieldLeftOut(): void
    {
        $xml = Customer::fromXml(self::fixture('customer-read-minimal'), TestTokens::organisation())
            ->edit()
            ->toXml('1000')
            ->toXml();

        self::assertStringNotContainsString('<invoicing>', $xml);
        self::assertStringNotContainsString('<groups', $xml);
        self::assertStringNotContainsString('<paymentconditions', $xml);
        self::assertStringNotContainsString('<collectmandate>', $xml);
        self::assertStringContainsString('<banks/>', $xml);
        self::assertStringContainsString(
            '<postingrule id="1" status="active"><currency/><amount/><description/><lines/></postingrule>',
            $xml,
        );
        self::assertStringContainsString(
            '<payavailable>false</payavailable><meansofpayment>none</meansofpayment><paycode/>',
            $xml,
        );
    }

    public function testThrowsOnAnotherDimensionType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/type is "CRD", expected DEB.');

        Customer::fromXml(
            self::fixture(
                'customer-read',
                ['<type name="Debiteuren" shortname="Debiteuren">DEB</type>' => '<type>CRD</type>'],
            ),
            TestTokens::organisation(),
        );
    }

    public function testThrowsOnAnUnknownReminderSetting(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'dimension/creditmanagement/sendreminder is "sms", expected one of true, email, false.',
        );

        Customer::fromXml(
            self::fixture(
                'customer-read',
                ['<sendreminder>email</sendreminder>' => '<sendreminder>sms</sendreminder>'],
            ),
            TestTokens::organisation(),
        );
    }

    public function testThrowsOnAnInvalidCreditLimit(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/creditmanagement/basecreditlimit is "5.000,00", expected an amount.');

        Customer::fromXml(
            self::fixture(
                'customer-read',
                ['<basecreditlimit>5000.00</basecreditlimit>' => '<basecreditlimit>5.000,00</basecreditlimit>'],
            ),
            TestTokens::organisation(),
        );
    }

    public function testThrowsOnAMissingBalanceAccount(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/financials/substitutewith is missing.');

        Customer::fromXml(
            self::fixture('customer-read', ['~<substitutewith[^>]*>1300</substitutewith>~' => '']),
            TestTokens::organisation(),
        );
    }

    /**
     * The `<dimension>` of a SOAP fixture, optionally with replacements in the XML (keys that
     * start with `~` are regular expressions).
     *
     * @param array<string, string> $replace
     */
    public static function fixture(string $name, array $replace = []): XmlElement
    {
        $soap = new DOMDocument();
        $soap->load(dirname(__DIR__, 2) . '/Fixtures/soap/' . $name . '.xml');

        $xml = (string) $soap->getElementsByTagName('ProcessXmlStringResult')->item(0)?->textContent;

        foreach ($replace as $search => $replacement) {
            $xml = str_starts_with($search, '~')
                ? (string) preg_replace($search, $replacement, $xml)
                : str_replace($search, $replacement, $xml);
        }

        return XmlElement::root(ProcessXmlResult::fromXml($xml));
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
