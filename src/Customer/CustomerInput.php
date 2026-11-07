<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\PaymentCondition;
use SolveBeam\Twinfield\Dimension\PostingRuleInput;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;

/**
 * A customer to create or update. Immutable: every method returns a new input.
 *
 *   $input = CustomerInput::create('Example B.V.')
 *       ->website('www.example.com')
 *       ->dueDays(30)
 *       ->vatCode('VH')
 *       ->eInvoicing('invoices@example.com')
 *       ->directDebit('SEPANLDD')
 *       ->collectMandate('MANDATE-001', new DateTimeImmutable('2026-01-15'))
 *       ->collectionSchema(CollectionSchema::Core)
 *       ->creditLimit('5000.00')
 *       ->sendReminder(SendReminder::ByEmail, 'finance@example.com')
 *       ->address(
 *           AddressInput::invoice('Example B.V.')
 *               ->field(2, 'Main street 1')
 *               ->postcode('1234 AB')
 *               ->city('Amsterdam')
 *               ->country('NL')
 *               ->vatNumber('NL001234567B01')
 *               ->default(),
 *       )
 *       ->bank(BankInput::create()->iban('NL91ABNA0417164300')->ascription('Example B.V.')->default())
 *       ->paymentCondition(8, '2');
 *
 * Without code() Twinfield gives the customer the next free code of the DEB dimension type.
 *
 * Only what you set is sent: anything else gets the default of the dimension type when
 * creating, and stays as it is when updating. Setting a value to null clears it. For an
 * existing customer, start from `$customer->edit()`, which sets every value.
 *
 * Lists (addresses, bank accounts, groups, payment conditions) are sent as a whole once you
 * set them: an address you leave out is removed. Posting rules you remove are sent with
 * status `deleted`.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/customers/
 */
final readonly class CustomerInput
{
    /**
     * Elements in the order of the documentation; `a/b` is element `b` in `a`.
     */
    private const ELEMENTS = [
        'shortname',
        'beginperiod',
        'beginyear',
        'endperiod',
        'endyear',
        'website',
        'financials/duedays',
        'financials/payavailable',
        'financials/meansofpayment',
        'financials/paycode',
        'financials/ebilling',
        'financials/ebillmail',
        'financials/substitutewith',
        'financials/vatcode',
        'financials/collectmandate/id',
        'financials/collectmandate/signaturedate',
        'financials/collectionschema',
        'creditmanagement/responsibleuser',
        'creditmanagement/basecreditlimit',
        'creditmanagement/sendreminder',
        'creditmanagement/reminderemail',
        'creditmanagement/blocked',
        'creditmanagement/freetext1',
        'creditmanagement/freetext2',
        'creditmanagement/comment',
        'remittanceadvice/sendtype',
        'remittanceadvice/sendmail',
        'invoicing/discountarticle',
    ];

    /**
     * @param array<string, ?string> $values Element (see ELEMENTS) => value; null sends an
     *     empty element, which clears the value.
     * @param ?list<AddressInput> $addresses Null when not set: the addresses are not sent.
     * @param ?list<BankInput> $banks Null when not set.
     * @param ?list<string> $groups Null when not set.
     * @param ?list<PostingRuleInput> $postingRules Null when not set.
     * @param ?list<PaymentCondition> $paymentConditions Null when not set.
     */
    private function __construct(
        /** Code; null to let Twinfield give the next free code. */
        public ?string $code,
        public string $name,
        private array $values,
        public ?array $addresses,
        public ?array $banks,
        public ?array $groups,
        public ?array $postingRules,
        public ?array $paymentConditions,
    ) {
        if ($code !== null) {
            XmlValue::code($code);
        }

        if ($name === '') {
            throw new InvalidArgumentException('Customer name must not be empty.');
        }

        XmlValue::maxLength('customer name', $name, 80);

        if (count(array_filter($addresses ?? [], static fn (AddressInput $address): bool => $address->default)) > 1) {
            throw new InvalidArgumentException('A customer has at most one default address.');
        }

        if (count(array_filter($banks ?? [], static fn (BankInput $bank): bool => $bank->default)) > 1) {
            throw new InvalidArgumentException('A customer has at most one default bank account.');
        }
    }

    /**
     * @param string $name Name, at most 80 characters.
     */
    public static function create(string $name): self
    {
        return new self(null, $name, [], null, null, null, null, null);
    }

    /**
     * Code in the format of the DEB dimension type of the company, e.g. `1100`; null lets
     * Twinfield give the next free code.
     */
    public function code(?string $code): self
    {
        return $this->with(['code' => $code === '' ? null : $code]);
    }

    /**
     * Name, at most 80 characters.
     */
    public function name(string $name): self
    {
        return $this->with(['name' => $name]);
    }

    /**
     * Short name, at most 20 characters.
     */
    public function shortName(?string $shortName): self
    {
        return $this->set('shortname', self::maxLength('customer short name', $shortName, 20));
    }

    /**
     * Website, at most 40 characters, e.g. `www.example.com`.
     */
    public function website(?string $website): self
    {
        return $this->set('website', self::maxLength('customer website', $website, 40));
    }

    /**
     * First period the customer can be used in; null for no limit.
     */
    public function activeFrom(?FinancialPeriod $period): self
    {
        return $this->setMany([
            'beginperiod' => (string) ($period->number ?? 0),
            'beginyear' => (string) ($period->year ?? 0),
        ]);
    }

    /**
     * Last period the customer can be used in; null for no limit.
     */
    public function activeUntil(?FinancialPeriod $period): self
    {
        return $this->setMany([
            'endperiod' => (string) ($period->number ?? 0),
            'endyear' => (string) ($period->year ?? 0),
        ]);
    }

    /**
     * Payment term in days, e.g. `30`.
     */
    public function dueDays(int $days): self
    {
        if ($days < 0) {
            throw new InvalidArgumentException(sprintf('Due days must be 0 or more, %d given.', $days));
        }

        return $this->set('financials/duedays', (string) $days);
    }

    /**
     * Collects invoices by direct debit, with a payment file.
     *
     * @param ?string $payCode Payment type code, e.g. `SEPANLDD`.
     */
    public function directDebit(?string $payCode): self
    {
        return $this->setMany([
            'financials/payavailable' => 'true',
            'financials/meansofpayment' => MeansOfPayment::PaymentFile->value,
            'financials/paycode' => self::nullableCode($payCode),
        ]);
    }

    /**
     * No direct debit: the customer pays the invoices.
     */
    public function withoutDirectDebit(): self
    {
        return $this->setMany([
            'financials/payavailable' => 'false',
            'financials/meansofpayment' => MeansOfPayment::None->value,
            'financials/paycode' => null,
        ]);
    }

    /**
     * SEPA mandate to collect with: its ID (at most 35 characters) and signature date.
     */
    public function collectMandate(string $id, ?DateTimeInterface $signatureDate = null): self
    {
        if ($id === '') {
            throw new InvalidArgumentException('Mandate id must not be empty.');
        }

        XmlValue::maxLength('mandate id', $id, 35);

        return $this->setMany([
            'financials/collectmandate/id' => $id,
            'financials/collectmandate/signaturedate' => $signatureDate === null
                ? null
                : XmlValue::date($signatureDate),
        ]);
    }

    /**
     * SEPA scheme for direct debit.
     */
    public function collectionSchema(CollectionSchema $schema): self
    {
        return $this->set('financials/collectionschema', $schema->value);
    }

    /**
     * Sends invoices by email, to this address (at most 200 characters) when given.
     */
    public function eInvoicing(?string $email = null): self
    {
        $input = $this->set('financials/ebilling', 'true');

        return $email === null ? $input : $input->eInvoicingEmail($email);
    }

    /**
     * Sends invoices by post. The email address is kept.
     */
    public function withoutEInvoicing(): self
    {
        return $this->set('financials/ebilling', 'false');
    }

    /**
     * Email address for invoices, at most 200 characters.
     */
    public function eInvoicingEmail(?string $email): self
    {
        return $this->set('financials/ebillmail', self::maxLength('invoice email', $email, 200));
    }

    /**
     * Balance sheet account of the customer, e.g. `1300`. By default the account of the
     * DEB dimension type.
     */
    public function balanceAccount(string $account): self
    {
        return $this->set('financials/substitutewith', XmlValue::code($account));
    }

    /**
     * Default VAT code on invoices, e.g. `VH`.
     */
    public function vatCode(?string $vatCode): self
    {
        return $this->set('financials/vatcode', self::nullableCode($vatCode));
    }

    /**
     * Credit manager (user code).
     */
    public function responsibleUser(?string $user): self
    {
        return $this->set('creditmanagement/responsibleuser', self::maxLength('responsible user', $user, 16));
    }

    /**
     * Credit limit, e.g. `'5000.00'`; `'0.00'` for none.
     *
     * @param Amount|numeric-string $limit
     */
    public function creditLimit(Amount|string $limit): self
    {
        return $this->set('creditmanagement/basecreditlimit', Amount::of($limit)->value);
    }

    /**
     * Whether and how Twinfield sends payment reminders, and the email address for them.
     *
     *   ->sendReminder(SendReminder::ByEmail, 'finance@example.com')
     *   ->sendReminder(SendReminder::No)
     *
     * @throws InvalidArgumentException For SendReminder::ByEmail without email address.
     */
    public function sendReminder(SendReminder $sendReminder, ?string $email = null): self
    {
        $email = $email === '' ? null : $email;

        if ($sendReminder === SendReminder::ByEmail && $email === null) {
            throw new InvalidArgumentException('Reminders by email need an email address.');
        }

        return $this->setMany([
            'creditmanagement/sendreminder' => $sendReminder->value,
            'creditmanagement/reminderemail' => self::maxLength('reminder email', $email, 200),
        ]);
    }

    /**
     * Blocks the projects of the customer in time & expenses.
     */
    public function blocked(bool $blocked = true): self
    {
        return $this->set('creditmanagement/blocked', XmlValue::boolean($blocked));
    }

    /**
     * Right of use (free text 1).
     */
    public function rightOfUse(?bool $rightOfUse): self
    {
        return $this->set('creditmanagement/freetext1', $rightOfUse === null ? null : XmlValue::boolean($rightOfUse));
    }

    /**
     * Segment code (free text 2), at most 40 characters.
     */
    public function segmentCode(?string $segmentCode): self
    {
        return $this->set('creditmanagement/freetext2', self::maxLength('segment code', $segmentCode, 40));
    }

    /**
     * Credit management comment.
     */
    public function comment(?string $comment): self
    {
        return $this->set('creditmanagement/comment', $comment === '' ? null : $comment);
    }

    /**
     * Where to put the remittance advice.
     *
     * @throws InvalidArgumentException For RemittanceAdviceSendType::ByEmail without email address.
     */
    public function remittanceAdvice(RemittanceAdviceSendType $sendType, ?string $email = null): self
    {
        $email = $email === '' ? null : $email;

        if ($sendType === RemittanceAdviceSendType::ByEmail && $email === null) {
            throw new InvalidArgumentException('A remittance advice by email needs an email address.');
        }

        return $this->setMany([
            'remittanceadvice/sendtype' => $sendType->value,
            'remittanceadvice/sendmail' => self::maxLength('remittance advice email', $email, 200),
        ]);
    }

    /**
     * Article for discounts or premiums on invoices.
     */
    public function discountArticle(?string $article): self
    {
        return $this->set('invoicing/discountarticle', self::nullableCode($article));
    }

    /**
     * Adds an address, or replaces the address with the same id. A default address makes the
     * other addresses non-default.
     */
    public function address(AddressInput $address): self
    {
        $addresses = $this->addresses ?? [];

        if ($address->default) {
            $addresses = array_map(static fn (AddressInput $other): AddressInput => $other->default(false), $addresses);
        }

        return $this->with(['addresses' => self::replaceOrAppend($addresses, $address)]);
    }

    /**
     * Replaces all addresses; addresses you leave out are removed.
     */
    public function addresses(AddressInput ...$addresses): self
    {
        return $this->with(['addresses' => array_values($addresses)]);
    }

    /**
     * Removes the address with this id. Start from `$customer->edit()`.
     *
     * @throws InvalidArgumentException When there is no address with this id.
     */
    public function withoutAddress(int $id): self
    {
        return $this->with(['addresses' => self::remove($this->addresses, $id, 'address', 'addresses')]);
    }

    /**
     * Adds a bank account, or replaces the account with the same id. A default account makes
     * the other accounts non-default.
     */
    public function bank(BankInput $bank): self
    {
        $banks = $this->banks ?? [];

        if ($bank->default) {
            $banks = array_map(static fn (BankInput $other): BankInput => $other->default(false), $banks);
        }

        return $this->with(['banks' => self::replaceOrAppend($banks, $bank)]);
    }

    /**
     * Replaces all bank accounts; accounts you leave out are removed.
     */
    public function banks(BankInput ...$banks): self
    {
        return $this->with(['banks' => array_values($banks)]);
    }

    /**
     * Removes the bank account with this id. Start from `$customer->edit()`.
     *
     * @throws InvalidArgumentException When there is no bank account with this id.
     */
    public function withoutBank(int $id): self
    {
        return $this->with(['banks' => self::remove($this->banks, $id, 'bank account', 'bank accounts')]);
    }

    /**
     * Adds the customer to a dimension group.
     */
    public function group(string $group): self
    {
        return $this->groups(...array_unique([...$this->groups ?? [], $group]));
    }

    /**
     * Replaces all dimension groups.
     */
    public function groups(string ...$groups): self
    {
        foreach ($groups as $group) {
            XmlValue::code($group);
        }

        return $this->with(['groups' => array_values($groups)]);
    }

    /**
     * Adds a posting rule, or replaces the rule with the same id.
     */
    public function postingRule(PostingRuleInput $rule): self
    {
        return $this->with(['postingRules' => self::replaceOrAppend($this->postingRules ?? [], $rule)]);
    }

    /**
     * Sets the posting rules to send. To delete an existing rule, use withoutPostingRule().
     */
    public function postingRules(PostingRuleInput ...$rules): self
    {
        return $this->with(['postingRules' => array_values($rules)]);
    }

    /**
     * Deletes the posting rule with this id. Start from `$customer->edit()`.
     *
     * @throws InvalidArgumentException When there is no posting rule with this id.
     */
    public function withoutPostingRule(int $id): self
    {
        foreach ($this->postingRules ?? [] as $index => $rule) {
            if ($rule->id === $id) {
                $rules = $this->postingRules;
                $rules[$index] = $rule->deleted();

                return $this->with(['postingRules' => $rules]);
            }
        }

        throw new InvalidArgumentException(sprintf('There is no posting rule %d: start from $customer->edit().', $id));
    }

    /**
     * Adds a payment discount, e.g. `paymentCondition(8, '2')` for 2% within 8 days.
     *
     * @param numeric-string $discountPercentage
     */
    public function paymentCondition(int $discountDays, string $discountPercentage): self
    {
        return $this->paymentConditions(
            ...[...$this->paymentConditions ?? [], PaymentCondition::of($discountDays, $discountPercentage)],
        );
    }

    /**
     * Replaces all payment discounts; `paymentConditions()` without arguments removes them.
     */
    public function paymentConditions(PaymentCondition ...$conditions): self
    {
        return $this->with(['paymentConditions' => array_values($conditions)]);
    }

    /**
     * The value that will be sent for an element, e.g. `value('financials/duedays')`, or null
     * when it is empty or not set. See has() to tell these apart.
     */
    public function value(string $element): ?string
    {
        return $this->values[$element] ?? null;
    }

    /**
     * True when the element will be sent, e.g. `has('financials/duedays')`.
     */
    public function has(string $element): bool
    {
        return array_key_exists($element, $this->values);
    }

    /**
     * The `<dimension>` request to save this customer in a company.
     */
    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('dimension');
        $dimension = $xml->root();

        $xml->add($dimension, 'office', $company);
        $xml->add($dimension, 'type', 'DEB');

        if ($this->code !== null) {
            $xml->add($dimension, 'code', $this->code);
        }

        $xml->add($dimension, 'name', $this->name);

        // Fixed values for customers, according to the documentation.
        $financials = $xml->document()->createElement('financials');
        $xml->add($financials, 'matchtype', 'customersupplier');
        $xml->add($financials, 'accounttype', 'inherit');
        $xml->add($financials, 'subanalyse', 'false');
        $xml->add($financials, 'substitutionlevel', '1');

        /** @var array<string, DOMElement> $parents */
        $parents = ['financials' => $financials];

        foreach (self::ELEMENTS as $path) {
            if (!array_key_exists($path, $this->values)) {
                continue;
            }

            // <financials> follows the dimension fields, also when none of its values are set.
            if (str_contains($path, '/') && $financials->parentNode === null) {
                $dimension->appendChild($financials);
            }

            $names = explode('/', $path);
            $name = array_pop($names);
            $parent = $dimension;
            $parentPath = '';

            foreach ($names as $parentName) {
                $parentPath .= ($parentPath === '' ? '' : '/') . $parentName;
                $parents[$parentPath] ??= $xml->add($parent, $parentName);
                $parent = $parents[$parentPath];
            }

            $xml->add($parent, $name, $this->values[$path]);
        }

        if ($financials->parentNode === null) {
            $dimension->appendChild($financials);
        }

        if ($this->addresses !== null) {
            $element = $xml->add($dimension, 'addresses');

            foreach ($this->addresses as $address) {
                $address->appendTo($xml, $element);
            }
        }

        if ($this->banks !== null) {
            $element = $xml->add($dimension, 'banks');

            foreach ($this->banks as $bank) {
                $bank->appendTo($xml, $element);
            }
        }

        if ($this->groups !== null) {
            $element = $xml->add($dimension, 'groups');

            foreach ($this->groups as $group) {
                $xml->add($element, 'group', $group);
            }
        }

        if ($this->postingRules !== null) {
            $element = $xml->add($dimension, 'postingrules');

            foreach ($this->postingRules as $rule) {
                $rule->appendTo($xml, $element);
            }
        }

        if ($this->paymentConditions !== null) {
            $element = $xml->add($dimension, 'paymentconditions');

            foreach ($this->paymentConditions as $condition) {
                $item = $xml->add($element, 'paymentcondition');
                $xml->add($item, 'discountdays', (string) $condition->discountDays);
                $xml->add($item, 'discountpercentage', $condition->discountPercentage);
            }
        }

        return $xml;
    }

    private function set(string $element, ?string $value): self
    {
        return $this->setMany([$element => $value]);
    }

    /**
     * @param array<string, ?string> $values
     */
    private function setMany(array $values): self
    {
        return $this->with(['values' => [...$this->values, ...$values]]);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }

    private static function maxLength(string $field, ?string $value, int $max): ?string
    {
        return $value === null || $value === '' ? null : XmlValue::maxLength($field, $value, $max);
    }

    private static function nullableCode(?string $code): ?string
    {
        return $code === null || $code === '' ? null : XmlValue::code($code);
    }

    /**
     * @template T of AddressInput|BankInput|PostingRuleInput
     *
     * @param list<T> $items
     * @param T $item
     *
     * @return list<T>
     */
    private static function replaceOrAppend(array $items, AddressInput|BankInput|PostingRuleInput $item): array
    {
        if ($item->id !== null) {
            foreach ($items as $index => $existing) {
                if ($existing->id === $item->id) {
                    $items[$index] = $item;

                    return $items;
                }
            }
        }

        $items[] = $item;

        return $items;
    }

    /**
     * @template T of AddressInput|BankInput
     *
     * @param ?list<T> $items
     *
     * @return list<T>
     */
    private static function remove(?array $items, int $id, string $label, string $plural): array
    {
        if ($items === null) {
            throw new LogicException(sprintf('There are no %s to remove from: start from $customer->edit().', $plural));
        }

        $remaining = array_values(array_filter(
            $items,
            static fn (AddressInput|BankInput $item): bool => $item->id !== $id,
        ));

        if (count($remaining) === count($items)) {
            throw new InvalidArgumentException(sprintf('There is no %s %d.', $label, $id));
        }

        return $remaining;
    }
}
