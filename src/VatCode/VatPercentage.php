<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A percentage of a VAT code from a date on (a `<percentage>` line), as Twinfield returns it.
 * It applies until the next percentage of the code starts.
 *
 *   $percentage = $vatCode->percentageOn(new DateTimeImmutable('2026-03-15'));
 *   echo $percentage?->percentage, '% from ', $percentage?->from->format('Y-m-d'), PHP_EOL;
 *   echo $percentage?->vatOn('100.00'), PHP_EOL;   // 21.00
 */
final readonly class VatPercentage
{
    /** @var non-empty-list<VatAccount> */
    public array $accounts;

    /**
     * @param list<VatAccount> $accounts At least one.
     */
    public function __construct(
        /** First day the percentage applies; the time is always 00:00. */
        public DateTimeImmutable $from,
        /** The VAT rate, e.g. `21`; 0 to 100. */
        public Percentage $percentage,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
        public VatCodeStatus $status,
        /** True when transactions use the percentage. */
        public bool $inUse,
        public DateTimeImmutable $created,
        /** Code of the user who added the percentage. */
        public string $createdBy,
        /**
         * `discountaccount`; null when not set. Not in the documentation and empty in all live
         * responses so far, so its use is unknown.
         */
        public ?NamedReference $discountAccount,
        /**
         * `writeoffaccount`; null when not set. Not in the documentation and empty in all live
         * responses so far, so its use is unknown.
         */
        public ?NamedReference $writeOffAccount,
        array $accounts,
    ) {
        VatPercentageInput::validateRate($percentage);

        if ($name === '' || $createdBy === '') {
            throw new InvalidArgumentException('VAT percentage name and user must not be empty.');
        }

        if ($shortName === '') {
            throw new InvalidArgumentException('VAT percentage short name must not be empty: use null for none.');
        }

        if ($accounts === []) {
            throw new InvalidArgumentException(sprintf(
                'VAT percentage from %s must have at least one account.',
                $from->format('Y-m-d'),
            ));
        }

        $this->accounts = $accounts;
    }

    /**
     * From a `<percentage>` element of `<vat><percentages>`.
     *
     * @internal
     */
    public static function fromXml(XmlElement $line): self
    {
        try {
            $from = XmlValue::toDate($line->string('date'));
        } catch (InvalidArgumentException) {
            throw $line->child('date')->invalid('a date yyyymmdd');
        }

        try {
            $percentage = Percentage::fromString($line->string('percentage'));
        } catch (InvalidArgumentException) {
            throw $line->child('percentage')->invalid('a percentage, e.g. 21.000000000');
        }

        // Left out by older responses (and by the documentation); empty when not set.
        $discountAccount = $line->nullableChild('discountaccount');
        $writeOffAccount = $line->nullableChild('writeoffaccount');

        try {
            return new self(
                from: $from,
                percentage: $percentage,
                name: $line->string('name'),
                // Optional in Twinfield: empty when no short name is set.
                shortName: $line->nullableString('shortname'),
                status: $line->enumAttribute('status', VatCodeStatus::class),
                inUse: $line->boolAttribute('inuse'),
                created: $line->dateTime('created'),
                createdBy: $line->string('user'),
                discountAccount: $discountAccount === null ? null : NamedReference::nullable($discountAccount),
                writeOffAccount: $writeOffAccount === null ? null : NamedReference::nullable($writeOffAccount),
                accounts: array_map(VatAccount::fromXml(...), $line->child('accounts')->list('account')),
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s: %s', $line->path, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * The VAT on an amount without VAT, rounded half away from zero to cents: 21% of `'10.05'`
     * gives `2.11`. Twinfield calculates VAT per line, so the sum of the lines may differ a cent
     * from the VAT on the total.
     *
     * @param Amount|numeric-string $amount Amount without VAT.
     */
    public function vatOn(Amount|string $amount): Amount
    {
        return $this->percentage->applyTo($amount);
    }

    /**
     * Input with the values and accounts of this percentage, to change and pass to a VatCodeInput.
     */
    public function edit(): VatPercentageInput
    {
        return VatPercentageInput::from($this->from, $this->percentage)
            ->name($this->name)
            ->shortName($this->shortName)
            ->withAccounts(...array_map(
                static fn (VatAccount $account): VatAccountInput => $account->edit(),
                $this->accounts,
            ));
    }
}
