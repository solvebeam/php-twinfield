<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Customer;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Customer\CollectionSchema;
use SolveBeam\Twinfield\Customer\Customer;
use SolveBeam\Twinfield\Customer\CustomerInput;
use SolveBeam\Twinfield\Customer\SendReminder;
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\PostingRuleInput;
use SolveBeam\Twinfield\Dimension\PostingRuleLine;
use SolveBeam\Twinfield\Dimension\RemittanceAdviceSendType;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Period\Period;

final class CustomerInputTest extends TestCase
{
    public function testANewCustomerOnlySendsTheNameAndFixedValues(): void
    {
        self::assertSame(
            '<dimension><office>1000</office><type>DEB</type><name>Example B.V.</name>'
            . '<financials><matchtype>customersupplier</matchtype><accounttype>inherit</accounttype>'
            . '<subanalyse>false</subanalyse><substitutionlevel>1</substitutionlevel></financials></dimension>',
            CustomerInput::create('Example B.V.')->toXml('1000')->toXml(),
        );
    }

    public function testBuildsAFullCustomer(): void
    {
        $input = CustomerInput::create('Example B.V.')
            ->code('1100')
            ->shortName('Example')
            ->website('www.example.com')
            ->activeFrom(Period::of(2026, 1))
            ->dueDays(30)
            ->vatCode('VH')
            ->balanceAccount('1300')
            ->eInvoicing('invoices@example.com')
            ->directDebit('SEPANLDD')
            ->collectMandate('MANDATE-001', new DateTimeImmutable('2026-01-15'))
            ->collectionSchema(CollectionSchema::Core)
            ->creditLimit('5000')
            ->sendReminder(SendReminder::ByEmail, 'finance@example.com')
            ->remittanceAdvice(RemittanceAdviceSendType::ToFileManager)
            ->address(
                AddressInput::invoice('Example B.V.')
                    ->city('Amsterdam')
                    ->country('NL')
                    ->vatNumber('NL001234567B01')
                    ->default(),
            )
            ->bank(BankInput::create()->iban('NL91 ABNA 0417 1643 00')->default())
            ->group('RETAIL')
            ->postingRule(PostingRuleInput::create('EUR', '100')->line(PostingRuleLine::create('8000', '100')))
            ->paymentCondition(8, '2');

        $xml = $input->toXml('1000')->toXml();

        self::assertStringStartsWith(
            '<dimension><office>1000</office><type>DEB</type><code>1100</code><name>Example B.V.</name>'
            . '<shortname>Example</shortname><beginperiod>1</beginperiod><beginyear>2026</beginyear>'
            . '<website>www.example.com</website><financials>',
            $xml,
        );
        self::assertStringContainsString(
            '<duedays>30</duedays><payavailable>true</payavailable><meansofpayment>paymentfile</meansofpayment>'
            . '<paycode>SEPANLDD</paycode><ebilling>true</ebilling><ebillmail>invoices@example.com</ebillmail>'
            . '<substitutewith>1300</substitutewith><vatcode>VH</vatcode>'
            . '<collectmandate><id>MANDATE-001</id><signaturedate>20260115</signaturedate></collectmandate>'
            . '<collectionschema>core</collectionschema></financials>',
            $xml,
        );
        self::assertStringContainsString(
            '<creditmanagement><basecreditlimit>5000.00</basecreditlimit><sendreminder>email</sendreminder>'
            . '<reminderemail>finance@example.com</reminderemail></creditmanagement>'
            . '<remittanceadvice><sendtype>ToFileManager</sendtype><sendmail/></remittanceadvice>',
            $xml,
        );
        self::assertStringContainsString(
            '<address type="invoice" default="true"><name>Example B.V.</name>'
                . '<country>NL</country><city>Amsterdam</city>',
            $xml,
        );
        self::assertStringContainsString('<field4>NL001234567B01</field4>', $xml);
        self::assertStringContainsString('<bank default="true"><ascription/>', $xml);
        self::assertStringContainsString('<iban>NL91ABNA0417164300</iban>', $xml);
        self::assertStringContainsString('<groups><group>RETAIL</group></groups>', $xml);
        self::assertStringContainsString(
            '<postingrules><postingrule status="active"><currency>EUR</currency><amount>100.00</amount><description/>'
            . '<lines><line><office/><dimension1>8000</dimension1><dimension2/>'
            . '<dimension3/><ratio>100</ratio><vatcode/><description/></line></lines>'
            . '</postingrule></postingrules>',
            $xml,
        );
        self::assertStringEndsWith(
            '<paymentconditions><paymentcondition><discountdays>8</discountdays>'
                . '<discountpercentage>2</discountpercentage></paymentcondition></paymentconditions></dimension>',
            $xml,
        );
    }

    public function testIsImmutable(): void
    {
        $input = CustomerInput::create('Example B.V.');
        $changed = $input->dueDays(14)->address(AddressInput::invoice());

        self::assertFalse($input->has('financials/duedays'));
        self::assertNull($input->addresses);
        self::assertSame('14', $changed->value('financials/duedays'));
        self::assertCount(1, $changed->addresses ?? []);
    }

    public function testNullClearsAValue(): void
    {
        $input = CustomerInput::create('Example B.V.')->website('www.example.com')->website(null);

        self::assertTrue($input->has('website'));
        self::assertNull($input->value('website'));
        self::assertStringContainsString('<website/>', $input->toXml('1000')->toXml());
    }

    public function testActiveFromNullMeansNoLimit(): void
    {
        $xml = CustomerInput::create('Example B.V.')->activeFrom(null)->activeUntil(null)->toXml('1000')->toXml();

        self::assertStringContainsString(
            '<beginperiod>0</beginperiod><beginyear>0</beginyear><endperiod>0</endperiod><endyear>0</endyear>',
            $xml,
        );
    }

    public function testWithoutDirectDebitClearsThePayCode(): void
    {
        $xml = CustomerInput::create('Example B.V.')
            ->directDebit('SEPANLDD')
            ->withoutDirectDebit()
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString(
            '<payavailable>false</payavailable><meansofpayment>none</meansofpayment><paycode/>',
            $xml,
        );
    }

    public function testWithoutEInvoicingKeepsTheEmailAddress(): void
    {
        $input = CustomerInput::create('Example B.V.')->eInvoicing('invoices@example.com')->withoutEInvoicing();

        self::assertSame('false', $input->value('financials/ebilling'));
        self::assertSame('invoices@example.com', $input->value('financials/ebillmail'));
    }

    public function testADefaultAddressMakesTheOthersNonDefault(): void
    {
        $input = CustomerInput::create('Example B.V.')
            ->address(AddressInput::invoice('First')->default())
            ->address(AddressInput::postal('Second')->default());

        self::assertSame(
            [false, true],
            array_map(static fn (AddressInput $address): bool => $address->default, $input->addresses ?? []),
        );
    }

    public function testADefaultBankMakesTheOthersNonDefault(): void
    {
        $input = CustomerInput::create('Example B.V.')
            ->bank(BankInput::create()->accountNumber('1')->default())
            ->bank(BankInput::create()->accountNumber('2')->default());

        self::assertSame(
            [false, true],
            array_map(static fn (BankInput $bank): bool => $bank->default, $input->banks ?? []),
        );
    }

    public function testRefusesTwoDefaultAddresses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A customer has at most one default address.');

        CustomerInput::create('Example B.V.')->addresses(
            AddressInput::invoice()->default(),
            AddressInput::postal()->default(),
        );
    }

    public function testAnAddressWithAnIdReplacesThatAddress(): void
    {
        $edit = Customer::fromXml(CustomerTest::fixture('customer-read'), TestTokens::organisation())->edit();
        $address = $edit->addresses[1] ?? self::fail('Expected a second address.');

        $input = $edit->address($address->city('Rotterdam'));

        self::assertCount(2, $input->addresses ?? []);
        self::assertSame('Rotterdam', $input->addresses[1]->city ?? null);
    }

    public function testRemovesAnAddressAndABank(): void
    {
        $input = Customer::fromXml(CustomerTest::fixture('customer-read'), TestTokens::organisation())
            ->edit()
            ->withoutAddress(2)
            ->withoutBank(1);

        self::assertSame(
            [1],
            array_map(static fn (AddressInput $address): ?int => $address->id, $input->addresses ?? []),
        );
        self::assertSame([], $input->banks);
        self::assertStringContainsString('<banks/>', $input->toXml('1000')->toXml());
    }

    public function testRefusesToRemoveAnUnknownAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no address 9.');

        Customer::fromXml(CustomerTest::fixture('customer-read'), TestTokens::organisation())->edit()->withoutAddress(
            9,
        );
    }

    public function testRemovingNeedsTheCurrentAddresses(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('There are no addresses to remove from: start from $customer->edit().');

        CustomerInput::create('Example B.V.')->withoutAddress(1);
    }

    public function testDeletesAPostingRule(): void
    {
        $xml = Customer::fromXml(CustomerTest::fixture('customer-read'), TestTokens::organisation())
            ->edit()
            ->withoutPostingRule(1)
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString('<postingrule id="1" status="deleted"><currency>EUR</currency>', $xml);
    }

    public function testGroupsAreUnique(): void
    {
        self::assertSame(['A', 'B'], CustomerInput::create('Example B.V.')->group('A')->group('B')->group('A')->groups);
    }

    public function testReminderByEmailNeedsAnEmailAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reminders by email need an email address.');

        CustomerInput::create('Example B.V.')->sendReminder(SendReminder::ByEmail);
    }

    public function testRemittanceAdviceByEmailNeedsAnEmailAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CustomerInput::create('Example B.V.')->remittanceAdvice(RemittanceAdviceSendType::ByEmail);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'empty name' => [static fn () => CustomerInput::create(''), 'Customer name must not be empty.'];
        yield 'long name' => [
            static fn () => CustomerInput::create(str_repeat('a', 81)),
            'Customer name must be at most 80 characters',
        ];
        yield 'invalid code' => [static fn () => CustomerInput::create('A')->code('ab c'), 'is not a code'];
        yield 'long short name' => [
            static fn () => CustomerInput::create('A')->shortName(str_repeat('a', 21)),
            'Customer short name must be at most 20',
        ];
        yield 'long website' => [
            static fn () => CustomerInput::create('A')->website(str_repeat('a', 41)),
            'Customer website must be at most 40',
        ];
        yield 'negative due days' => [
            static fn () => CustomerInput::create('A')->dueDays(-1),
            'Due days must be 0 or more',
        ];
        yield 'float credit limit' => [
            static fn () => CustomerInput::create('A')->creditLimit('1,50'),
            'is not an amount',
        ];
        yield 'long mandate' => [
            static fn () => CustomerInput::create('A')->collectMandate(str_repeat('M', 36)),
            'Mandate id must be at most 35',
        ];
        yield 'invalid percentage' => [
            static fn () => CustomerInput::create('A')->paymentCondition(8, '101'),
            'is not a discount percentage',
        ];
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
