<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Supplier;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\Address;
use SolveBeam\Twinfield\Dimension\Bank;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Dimension\PaymentCondition;
use SolveBeam\Twinfield\Dimension\PostingRule;
use SolveBeam\Twinfield\Dimension\RelationShortcuts;
use SolveBeam\Twinfield\Dimension\RemittanceAdvice;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;

/**
 * A supplier (dimension type `CRD`) as Twinfield returns it.
 *
 *   $supplier = $twinfield->suppliers()->get('2000');
 *
 *   echo $supplier->code, ' ', $supplier->name, PHP_EOL;
 *   echo $supplier->financials->dueDays, ' days', PHP_EOL;
 *   echo $supplier->defaultBank()?->iban, PHP_EOL;
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/suppliers/
 */
final readonly class Supplier
{
    use RelationShortcuts;

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
        /** Code in the format of the CRD dimension type of the company, e.g. `2000`. */
        public string $code,
        /** Unique ID of the dimension, e.g. `c6c05844-c075-4b51-a4ca-6f30f45d5a12`. */
        public string $uid,
        /** Name, at most 80 characters. */
        public string $name,
        /** Short name, at most 20 characters; null when not set. */
        public ?string $shortName,
        public DimensionStatus $status,
        /** True when the supplier is used in a transaction. Deleting it then hides it. */
        public bool $inUse,
        public DimensionBehaviour $behaviour,
        /** Number of times the settings were changed. */
        public int $touched,
        /** Last change. Null when Twinfield leaves it out: it is not in every documented response. */
        public ?DateTimeImmutable $modified,
        /** Website, at most 40 characters; null when not set. */
        public ?string $website,
        /** First period the supplier can be used in; null for no limit. */
        public ?FinancialPeriod $activeFrom,
        /** Last period the supplier can be used in; null for no limit. */
        public ?FinancialPeriod $activeUntil,
        public SupplierFinancials $financials,
        /** Null when Twinfield leaves it out: it is not in the documented response. */
        public ?RemittanceAdvice $remittanceAdvice,
        public array $addresses,
        public array $banks,
        /** Dimension group codes. */
        public array $groups,
        /** Posting rules: how an invoice amount is split over accounts when booking a purchase invoice. */
        public array $postingRules,
        public array $paymentConditions,
    ) {
        foreach (['company' => $company, 'code' => $code, 'uid' => $uid, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Supplier %s must not be empty.', $field));
            }
        }

        if ($touched < 0) {
            throw new InvalidArgumentException('Supplier touched count must be 0 or more.');
        }
    }

    /**
     * From a `<dimension>` element of a read or save response.
     */
    public static function fromXml(XmlElement $dimension): self
    {
        $type = $dimension->string('type');

        if ($type !== 'CRD') {
            throw $dimension->child('type')->invalid('CRD');
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
            financials: SupplierFinancials::fromXml($dimension->child('financials')),
            remittanceAdvice: $remittanceAdvice === null ? null : RemittanceAdvice::fromXml($remittanceAdvice),
            addresses: array_map(Address::fromXml(...), $dimension->child('addresses')->list('address')),
            // Twinfield leaves out <groups>, <postingrules> and <paymentconditions> when they are
            // empty, and sends an empty <banks/> when there are no bank accounts.
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
     * Input with all values of this supplier, to change and pass to update() or save().
     *
     *   $suppliers->update($supplier->edit()->dueDays(14));
     *
     * It carries every address, bank account, group, posting rule and payment condition, so
     * an update keeps what you do not change.
     */
    public function edit(): SupplierInput
    {
        $financials = $this->financials;

        $input = SupplierInput::create($this->name)
            ->code($this->code)
            ->shortName($this->shortName)
            ->website($this->website)
            ->activeFrom($this->activeFrom)
            ->activeUntil($this->activeUntil)
            ->dueDays($financials->dueDays)
            ->balanceAccount($financials->balanceAccount)
            ->relationsReference($financials->relationsReference)
            ->vatCode($financials->vatCode)
            ->addresses(...array_map(static fn (Address $address) => $address->edit(), $this->addresses))
            ->banks(...array_map(static fn (Bank $bank) => $bank->edit(), $this->banks));

        $input = match ($financials->meansOfPayment) {
            MeansOfPayment::PaymentFile => $input->creditTransfer($financials->payCode),
            MeansOfPayment::Cheques => $input->cheques(),
            MeansOfPayment::None => $input->withoutPayments(),
        };

        // Twinfield leaves these out when they are empty, so they are only sent when set.
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

        if ($this->remittanceAdvice !== null) {
            $input = $input->remittanceAdvice($this->remittanceAdvice->sendType, $this->remittanceAdvice->email);
        }

        return $input;
    }
}
