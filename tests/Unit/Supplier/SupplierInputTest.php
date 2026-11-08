<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Supplier;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\PayAddress;
use SolveBeam\Twinfield\Dimension\PostingRuleInput;
use SolveBeam\Twinfield\Dimension\PostingRuleLine;
use SolveBeam\Twinfield\Dimension\RemittanceAdviceSendType;
use SolveBeam\Twinfield\Dimension\WayOfPayment;
use SolveBeam\Twinfield\Supplier\Supplier;
use SolveBeam\Twinfield\Supplier\SupplierInput;
use SolveBeam\Twinfield\Tests\Unit\Customer\CustomerTest;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;

final class SupplierInputTest extends TestCase
{
    public function testANewSupplierOnlySendsTheNameAndFixedValues(): void
    {
        self::assertSame(
            '<dimension><office>1000</office><type>CRD</type><name>Example Supplies B.V.</name>'
            . '<financials><matchtype>customersupplier</matchtype><accounttype>inherit</accounttype>'
            . '<subanalyse>false</subanalyse><substitutionlevel>1</substitutionlevel></financials></dimension>',
            SupplierInput::create('Example Supplies B.V.')->toXml('1000')->toXml(),
        );
    }

    public function testBuildsAFullSupplier(): void
    {
        $input = SupplierInput::create('Example Supplies B.V.')
            ->code('2100')
            ->shortName('Supplies')
            ->website('www.example.com')
            ->activeFrom(FinancialPeriod::of(2026, 1))
            ->dueDays(30)
            ->creditTransfer('SEPANLCT')
            ->balanceAccount('1600')
            ->relationsReference('CUST-4711')
            ->vatCode('IH')
            ->remittanceAdvice(RemittanceAdviceSendType::ByEmail, 'accounts@example.com')
            ->address(AddressInput::invoice('Example Supplies B.V.')->city('Amsterdam')->country('NL')->vatNumber('NL001234567B01')->default())
            ->bank(BankInput::create()->iban('NL91 ABNA 0417 1643 00')->ascription('Example Supplies B.V.')->default())
            ->group('2REL')
            ->postingRule(PostingRuleInput::create('EUR', '121')->line(PostingRuleLine::create('4500', '100')->vatCode('IH')))
            ->paymentCondition(5, '2.75');

        $xml = $input->toXml('1000')->toXml();

        self::assertStringStartsWith(
            '<dimension><office>1000</office><type>CRD</type><code>2100</code><name>Example Supplies B.V.</name>'
            . '<shortname>Supplies</shortname><beginperiod>1</beginperiod><beginyear>2026</beginyear>'
            . '<website>www.example.com</website><financials>',
            $xml,
        );
        self::assertStringContainsString(
            '<substitutionlevel>1</substitutionlevel><duedays>30</duedays><payavailable>true</payavailable>'
            . '<meansofpayment>paymentfile</meansofpayment><paycode>SEPANLCT</paycode>'
            . '<substitutewith>1600</substitutewith><relationsreference>CUST-4711</relationsreference>'
            . '<vatcode>IH</vatcode></financials>'
            . '<remittanceadvice><sendtype>ByEmail</sendtype><sendmail>accounts@example.com</sendmail></remittanceadvice>',
            $xml,
        );
        self::assertStringContainsString('<address type="invoice" default="true"><name>Example Supplies B.V.</name><country>NL</country><city>Amsterdam</city>', $xml);
        self::assertStringContainsString('<bank default="true"><ascription>Example Supplies B.V.</ascription>', $xml);
        self::assertStringContainsString('<iban>NL91ABNA0417164300</iban>', $xml);
        self::assertStringContainsString('<groups><group>2REL</group></groups>', $xml);
        self::assertStringContainsString('<dimension1>4500</dimension1><dimension2/><dimension3/><ratio>100</ratio><vatcode>IH</vatcode>', $xml);
        self::assertStringEndsWith(
            '<paymentconditions><paymentcondition><discountdays>5</discountdays><discountpercentage>2.75</discountpercentage></paymentcondition></paymentconditions></dimension>',
            $xml,
        );

        // Not in use for suppliers, so never sent.
        foreach (['ebilling', 'ebillmail', 'collectmandate', 'collectionschema', 'creditmanagement', 'invoicing'] as $element) {
            self::assertStringNotContainsString('<' . $element, $xml);
        }
    }

    public function testMeansOfPaymentIsSetWithPayAvailableAndPayCode(): void
    {
        $input = SupplierInput::create('Example Supplies B.V.');

        self::assertStringContainsString(
            '<payavailable>true</payavailable><meansofpayment>paymentfile</meansofpayment><paycode>SEPANLCT</paycode>',
            $input->creditTransfer('SEPANLCT')->toXml('1000')->toXml(),
        );
        self::assertStringContainsString(
            '<payavailable>true</payavailable><meansofpayment>cheques</meansofpayment><paycode/>',
            $input->creditTransfer('SEPANLCT')->cheques()->toXml('1000')->toXml(),
        );
        self::assertStringContainsString(
            '<payavailable>false</payavailable><meansofpayment>none</meansofpayment><paycode/>',
            $input->creditTransfer('SEPANLCT')->withoutPayments()->toXml('1000')->toXml(),
        );
    }

    public function testIsImmutable(): void
    {
        $input = SupplierInput::create('Example Supplies B.V.');
        $changed = $input->dueDays(14)->bank(BankInput::create()->accountNumber('1'));

        self::assertFalse($input->has('financials/duedays'));
        self::assertNull($input->banks);
        self::assertSame('14', $changed->value('financials/duedays'));
        self::assertCount(1, $changed->banks ?? []);
    }

    public function testNullClearsAValue(): void
    {
        $input = SupplierInput::create('Example Supplies B.V.')->relationsReference('CUST-4711')->relationsReference(null);

        self::assertTrue($input->has('financials/relationsreference'));
        self::assertNull($input->value('financials/relationsreference'));
        self::assertStringContainsString('<relationsreference/>', $input->toXml('1000')->toXml());
    }

    public function testAcceptsTheVatCodeForNoVat(): void
    {
        self::assertSame('#', SupplierInput::create('Example Supplies B.V.')->vatCode('#')->value('financials/vatcode'));
    }

    public function testADefaultBankMakesTheOthersNonDefault(): void
    {
        $input = SupplierInput::create('Example Supplies B.V.')
            ->bank(BankInput::create()->accountNumber('1')->default())
            ->bank(BankInput::create()->accountNumber('2')->default());

        self::assertSame([false, true], array_map(static fn (BankInput $bank): bool => $bank->default, $input->banks ?? []));
    }

    public function testABankWithAnIdReplacesThatBank(): void
    {
        $edit = Supplier::fromXml(CustomerTest::fixture('supplier-read-full'))->edit();
        $bank = $edit->banks[1] ?? self::fail('Expected a second bank account.');

        $input = $edit->bank($bank->wayOfPayment(WayOfPayment::Cheque, PayAddress::Payer));

        self::assertCount(2, $input->banks ?? []);
        self::assertSame(PayAddress::Payer, $input->banks[1]->payAddress ?? null);
    }

    public function testRemovesAnAddressAndABank(): void
    {
        $input = Supplier::fromXml(CustomerTest::fixture('supplier-read-full'))->edit()->withoutAddress(2)->withoutBank(2);

        self::assertSame([1], array_map(static fn (AddressInput $address): ?int => $address->id, $input->addresses ?? []));
        self::assertSame([1], array_map(static fn (BankInput $bank): ?int => $bank->id, $input->banks ?? []));
    }

    public function testDeletesAPostingRule(): void
    {
        $xml = Supplier::fromXml(CustomerTest::fixture('supplier-read-full'))->edit()->withoutPostingRule(1)->toXml('1000')->toXml();

        self::assertStringContainsString('<postingrule id="1" status="deleted"><currency>EUR</currency>', $xml);
    }

    public function testRemovingNeedsTheCurrentBanks(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('There are no bank accounts to remove from: start from $supplier->edit().');

        SupplierInput::create('Example Supplies B.V.')->withoutBank(1);
    }

    public function testRefusesToDeleteAnUnknownPostingRule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no posting rule 2: start from $supplier->edit().');

        SupplierInput::create('Example Supplies B.V.')->withoutPostingRule(2);
    }

    public function testRefusesTwoDefaultBanks(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A supplier has at most one default bank account.');

        SupplierInput::create('Example Supplies B.V.')->banks(BankInput::create()->default(), BankInput::create()->default());
    }

    public function testRemittanceAdviceByEmailNeedsAnEmailAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A remittance advice by email needs an email address.');

        SupplierInput::create('Example Supplies B.V.')->remittanceAdvice(RemittanceAdviceSendType::ByEmail);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'empty name' => [static fn () => SupplierInput::create(''), 'Supplier name must not be empty.'];
        yield 'long name' => [static fn () => SupplierInput::create(str_repeat('a', 81)), 'Supplier name must be at most 80 characters'];
        yield 'invalid code' => [static fn () => SupplierInput::create('A')->code('ab c'), 'is not a code'];
        yield 'long short name' => [static fn () => SupplierInput::create('A')->shortName(str_repeat('a', 21)), 'Supplier short name must be at most 20'];
        yield 'long website' => [static fn () => SupplierInput::create('A')->website(str_repeat('a', 41)), 'Supplier website must be at most 40'];
        yield 'negative due days' => [static fn () => SupplierInput::create('A')->dueDays(-1), 'Due days must be 0 or more'];
        yield 'invalid pay code' => [static fn () => SupplierInput::create('A')->creditTransfer('sepa nl'), 'is not a code'];
        yield 'long reference' => [static fn () => SupplierInput::create('A')->relationsReference(str_repeat('R', 41)), 'Relations reference must be at most 40'];
        yield 'invalid VAT code' => [static fn () => SupplierInput::create('A')->vatCode('i h'), 'is not a code'];
        yield 'invalid account' => [static fn () => SupplierInput::create('A')->balanceAccount('16 00'), 'is not a code'];
        yield 'invalid percentage' => [static fn () => SupplierInput::create('A')->paymentCondition(8, '101'), 'is not a discount percentage'];
    }

    /**
     * @param callable(): mixed $create
     */
    #[DataProvider('invalidInput')]
    public function testRefusesInvalidInput(callable $create, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $create();
    }
}
