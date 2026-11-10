<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A VAT code as Twinfield returns it: its type (sales or purchase) and its percentages over
 * time, each with the accounts the VAT is posted on.
 *
 *   $vatCode = $twinfield->vatCodes()->get('VH');
 *   echo $vatCode->code, ' ', $vatCode->name, ' (', $vatCode->type->value, ')', PHP_EOL;
 *   echo $vatCode->percentageOn(new DateTimeImmutable('2026-03-15'))?->percentage, '%', PHP_EOL;
 *   echo $vatCode->vatOn('100.00', new DateTimeImmutable('2026-03-15')), PHP_EOL;   // 21.00
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/vat/
 */
final readonly class VatCode
{
    /** @var non-empty-list<VatPercentage> Sorted by start date, oldest first. */
    public array $percentages;

    /**
     * @param list<VatPercentage> $percentages In any order; at least one, each with its own start date.
     */
    public function __construct(
        /** Company (office) with its organisation. The read response has no company: it is the company of the request. */
        public CompanyReference $company,
        /** VAT code, e.g. `VH`. */
        public string $code,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
        public VatCodeType $type,
        public VatCodeStatus $status,
        /** Unique id of the VAT code, a UUID. */
        public string $uid,
        public DateTimeImmutable $created,
        public DateTimeImmutable $modified,
        /** Number of times the VAT code was changed. */
        public int $touched,
        /** Code of the user who created or changed the VAT code last. */
        public string $modifiedBy,
        array $percentages,
    ) {
        $required = [
            'code' => $code,
            'name' => $name,
            'uid' => $uid,
            'modifiedBy' => $modifiedBy,
        ];

        foreach ($required as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('VAT code %s must not be empty.', $field));
            }
        }

        if ($shortName === '') {
            throw new InvalidArgumentException('VAT code short name must not be empty: use null for none.');
        }

        if ($touched < 0) {
            throw new InvalidArgumentException(sprintf(
                'VAT code %s has touched %d, expected 0 or more.',
                $code,
                $touched,
            ));
        }

        if ($percentages === []) {
            throw new InvalidArgumentException(sprintf('VAT code %s must have at least one percentage.', $code));
        }

        usort($percentages, static fn (VatPercentage $a, VatPercentage $b): int => $a->from <=> $b->from);

        foreach ($percentages as $index => $percentage) {
            if ($index > 0 && $percentages[$index - 1]->from == $percentage->from) {
                throw new InvalidArgumentException(sprintf(
                    'VAT code %s has two percentages from %s.',
                    $code,
                    $percentage->from->format('Y-m-d'),
                ));
            }
        }

        $this->percentages = $percentages;
    }

    /**
     * From the `<vat>` element of a read response.
     *
     * @param CompanyReference $company The company of the request: the response does not have it.
     *
     * @internal
     */
    public static function fromXml(XmlElement $vat, CompanyReference $company): self
    {
        $percentages = array_map(VatPercentage::fromXml(...), $vat->child('percentages')->list('percentage'));

        try {
            return new self(
                company: $company,
                code: $vat->string('code'),
                name: $vat->string('name'),
                // Optional in Twinfield: empty when no short name is set.
                shortName: $vat->nullableString('shortname'),
                type: $vat->enum('type', VatCodeType::class),
                status: $vat->enumAttribute('status', VatCodeStatus::class),
                uid: $vat->string('uid'),
                created: $vat->dateTime('created'),
                modified: $vat->dateTime('modified'),
                touched: $vat->int('touched'),
                modifiedBy: $vat->string('user'),
                percentages: $percentages,
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s: %s', $vat->path, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * The percentage that applies on the date: the one with the latest start date on or before
     * it. Null when the date is before the first percentage.
     */
    public function percentageOn(DateTimeInterface $date): ?VatPercentage
    {
        $day = $date->format('Ymd');
        $found = null;

        foreach ($this->percentages as $percentage) {
            if ($percentage->from->format('Ymd') <= $day) {
                $found = $percentage;
            }
        }

        return $found;
    }

    /**
     * The percentage that applies today; null when the first percentage starts later.
     */
    public function currentPercentage(): ?VatPercentage
    {
        return $this->percentageOn(new DateTimeImmutable('today'));
    }

    /**
     * The percentage with the latest start date, which may be in the future.
     */
    public function latestPercentage(): VatPercentage
    {
        return $this->percentages[array_key_last($this->percentages)];
    }

    /**
     * The VAT on an amount without VAT on a date, rounded half away from zero to cents.
     *
     *   $twinfield->vatCodes()->get('VH')->vatOn('100.00', $invoiceDate);   // 21.00
     *
     * @param Amount|numeric-string $amount Amount without VAT.
     *
     * @throws InvalidArgumentException When the date is before the first percentage.
     */
    public function vatOn(Amount|string $amount, DateTimeInterface $date): Amount
    {
        $percentage = $this->percentageOn($date) ?? throw new InvalidArgumentException(sprintf(
            'VAT code %s has no percentage on %s: the first one starts on %s.',
            $this->code,
            $date->format('Y-m-d'),
            $this->percentages[0]->from->format('Y-m-d'),
        ));

        return $percentage->vatOn($amount);
    }

    /**
     * Input with all values and percentages of this VAT code, to change and pass to update()
     * or save().
     *
     *   $vatCodes->update($vatCode->edit()->newPercentageFrom(new DateTimeImmutable('2027-01-01'), '9'));
     */
    public function edit(): VatCodeInput
    {
        $input = VatCodeInput::create($this->code, $this->name, $this->type)->shortName($this->shortName);

        foreach ($this->percentages as $percentage) {
            $input = $input->percentage($percentage->edit());
        }

        return $input;
    }
}
