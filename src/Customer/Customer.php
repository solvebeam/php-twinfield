<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\Address;
use SolveBeam\Twinfield\Dimension\AddressType;
use SolveBeam\Twinfield\Dimension\Bank;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Dimension\PaymentCondition;
use SolveBeam\Twinfield\Dimension\PostingRule;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;

/**
 * A customer (dimension type `DEB`) as Twinfield returns it.
 *
 *   $customer = $twinfield->customers()->get('1000');
 *
 *   echo $customer->code, ' ', $customer->name, PHP_EOL;
 *   echo $customer->financials->dueDays, ' days', PHP_EOL;
 *   echo $customer->defaultAddress()?->city, PHP_EOL;
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/customers/
 */
final readonly class Customer
{
    /**
     * @param list<Address> $addresses
     * @param list<Bank> $banks
     * @param list<string> $groups
     * @param list<PostingRule> $postingRules
     * @param list<PaymentCondition> $paymentConditions
     */
    public function __construct(
        /** Company (office) code. */
        public string $company,
        /** Code in the format of the DEB dimension type of the company, e.g. `1000`. */
        public string $code,
        /** Unique ID of the dimension, e.g. `d245aefe-a0f4-4127-9075-630ce56f88a0`. */
        public string $uid,
        /** Name, at most 80 characters. */
        public string $name,
        /** Short name, at most 20 characters; null when not set. */
        public ?string $shortName,
        public DimensionStatus $status,
        /** True when the customer is used in a transaction. Deleting it then hides it. */
        public bool $inUse,
        public DimensionBehaviour $behaviour,
        /** Number of times the settings were changed. */
        public int $touched,
        /** Last change. Null when Twinfield leaves it out: it is not in every documented response. */
        public ?DateTimeImmutable $modified,
        /** Website, at most 40 characters; null when not set. */
        public ?string $website,
        /** First period the customer can be used in; null for no limit. */
        public ?FinancialPeriod $activeFrom,
        /** Last period the customer can be used in; null for no limit. */
        public ?FinancialPeriod $activeUntil,
        public CustomerFinancials $financials,
        public CreditManagement $creditManagement,
        /** Null when Twinfield leaves it out: it is not in the documented responses. */
        public ?RemittanceAdvice $remittanceAdvice,
        /** Article for discounts or premiums on invoices; null when not set. */
        public ?string $discountArticle,
        public array $addresses,
        public array $banks,
        /** Dimension group codes. */
        public array $groups,
        public array $postingRules,
        public array $paymentConditions,
    ) {
        foreach (['company' => $company, 'code' => $code, 'uid' => $uid, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Customer %s must not be empty.', $field));
            }
        }

        if ($touched < 0) {
            throw new InvalidArgumentException('Customer touched count must be 0 or more.');
        }
    }

    /**
     * From a `<dimension>` element of a read or save response.
     */
    public static function fromXml(XmlElement $dimension): self
    {
        $type = $dimension->string('type');

        if ($type !== 'DEB') {
            throw $dimension->child('type')->invalid('DEB');
        }

        $remittanceAdvice = $dimension->nullableChild('remittanceadvice');

        return new self(
            company: $dimension->string('office'),
            code: $dimension->string('code'),
            uid: $dimension->string('uid'),
            name: $dimension->string('name'),
            // Optional in Twinfield: empty when no short name is set.
            shortName: $dimension->nullableString('shortname'),
            status: $dimension->enumAttribute('status', DimensionStatus::class),
            inUse: $dimension->bool('inuse'),
            behaviour: $dimension->enum('behaviour', DimensionBehaviour::class),
            touched: $dimension->int('touched'),
            // Not in the save response of the documentation, but in every read response.
            modified: $dimension->nullableChild('modified') === null ? null : $dimension->dateTime('modified'),
            website: $dimension->nullableString('website'),
            activeFrom: self::period($dimension, 'beginyear', 'beginperiod'),
            activeUntil: self::period($dimension, 'endyear', 'endperiod'),
            financials: CustomerFinancials::fromXml($dimension->child('financials')),
            creditManagement: CreditManagement::fromXml($dimension->child('creditmanagement')),
            remittanceAdvice: $remittanceAdvice === null ? null : RemittanceAdvice::fromXml($remittanceAdvice),
            // Twinfield leaves out <invoicing>, <groups>, <postingrules> and <paymentconditions>
            // when they are empty.
            discountArticle: $dimension->nullableChild('invoicing')?->nullableString('discountarticle'),
            addresses: array_map(Address::fromXml(...), $dimension->child('addresses')->list('address')),
            banks: array_map(Bank::fromXml(...), $dimension->child('banks')->list('bank')),
            groups: array_map(
                static fn (XmlElement $group): string => $group->text() !== ''
                    ? $group->text()
                    : throw $group->invalid('a group code'),
                $dimension->nullableChild('groups')?->list('group') ?? [],
            ),
            postingRules: array_map(
                PostingRule::fromXml(...),
                $dimension->nullableChild('postingrules')?->list('postingrule') ?? [],
            ),
            paymentConditions: array_map(
                PaymentCondition::fromXml(...),
                $dimension->nullableChild('paymentconditions')?->list('paymentcondition') ?? [],
            ),
        );
    }

    /**
     * The default address, or null when the customer has no addresses.
     */
    public function defaultAddress(): ?Address
    {
        foreach ($this->addresses as $address) {
            if ($address->default) {
                return $address;
            }
        }

        return $this->addresses[0] ?? null;
    }

    /**
     * The address with this id, or null.
     */
    public function address(int $id): ?Address
    {
        foreach ($this->addresses as $address) {
            if ($address->id === $id) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Addresses of one type, e.g. `addressesOfType(AddressType::Postal)`.
     *
     * @return list<Address>
     */
    public function addressesOfType(AddressType $type): array
    {
        return array_values(array_filter(
            $this->addresses,
            static fn (Address $address): bool => $address->type === $type,
        ));
    }

    /**
     * The default bank account, or null when the customer has no bank accounts.
     */
    public function defaultBank(): ?Bank
    {
        foreach ($this->banks as $bank) {
            if ($bank->default) {
                return $bank;
            }
        }

        return $this->banks[0] ?? null;
    }

    /**
     * The bank account with this id, or null.
     */
    public function bank(int $id): ?Bank
    {
        foreach ($this->banks as $bank) {
            if ($bank->id === $id) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * VAT number of the default address (field 4).
     */
    public function vatNumber(): ?string
    {
        return $this->defaultAddress()?->vatNumber();
    }

    /**
     * Chamber of commerce number of the default address (field 5).
     */
    public function chamberOfCommerceNumber(): ?string
    {
        return $this->defaultAddress()?->chamberOfCommerceNumber();
    }

    /**
     * Input with all values of this customer, to change and pass to update() or save().
     *
     *   $customers->update($customer->edit()->dueDays(14));
     *
     * It carries every address, bank account, group, posting rule and payment condition, so
     * an update keeps what you do not change.
     */
    public function edit(): CustomerInput
    {
        $input = CustomerInput::create($this->name)
            ->code($this->code)
            ->shortName($this->shortName)
            ->website($this->website)
            ->activeFrom($this->activeFrom)
            ->activeUntil($this->activeUntil)
            ->dueDays($this->financials->dueDays)
            ->balanceAccount($this->financials->balanceAccount)
            ->vatCode($this->financials->vatCode)
            ->responsibleUser($this->creditManagement->responsibleUser)
            ->creditLimit($this->creditManagement->creditLimit)
            ->sendReminder($this->creditManagement->sendReminder, $this->creditManagement->reminderEmail)
            ->blocked($this->creditManagement->blocked)
            ->rightOfUse($this->creditManagement->rightOfUse)
            ->segmentCode($this->creditManagement->segmentCode)
            ->comment($this->creditManagement->comment)
            ->addresses(...array_map(static fn (Address $address) => $address->edit(), $this->addresses))
            ->banks(...array_map(static fn (Bank $bank) => $bank->edit(), $this->banks));

        // Twinfield leaves these out when they are empty, so they are only sent when set.
        if ($this->discountArticle !== null) {
            $input = $input->discountArticle($this->discountArticle);
        }

        if ($this->groups !== []) {
            $input = $input->groups(...$this->groups);
        }

        if ($this->postingRules !== []) {
            $input = $input->postingRules(
                ...array_map(static fn (PostingRule $rule) => $rule->edit(), $this->postingRules),
            );
        }

        if ($this->paymentConditions !== []) {
            $input = $input->paymentConditions(...$this->paymentConditions);
        }

        $financials = $this->financials;

        $input = $financials->eInvoicing ? $input->eInvoicing() : $input->withoutEInvoicing();
        $input = $input->eInvoicingEmail($financials->eInvoicingEmail);
        $input = $financials->directDebit
            ? $input->directDebit($financials->payCode)
            : $input->withoutDirectDebit();

        if ($financials->collectMandate !== null) {
            $mandate = $financials->collectMandate;
            $input = $input->collectMandate($mandate->id, $mandate->signatureDate);
        }

        if ($financials->collectionSchema !== null) {
            $input = $input->collectionSchema($financials->collectionSchema);
        }

        if ($this->remittanceAdvice !== null) {
            $input = $input->remittanceAdvice($this->remittanceAdvice->sendType, $this->remittanceAdvice->email);
        }

        return $input;
    }

    /**
     * A period from a year and period element; Twinfield uses 0 for "no limit".
     */
    private static function period(XmlElement $dimension, string $yearElement, string $periodElement): ?FinancialPeriod
    {
        $year = $dimension->int($yearElement);
        $period = $dimension->int($periodElement);

        return $year === 0 ? null : FinancialPeriod::of($year, $period);
    }
}
