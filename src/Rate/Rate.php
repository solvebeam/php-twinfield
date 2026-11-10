<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rate;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A rate of Twinfield Time & Expenses as Twinfield returns it: the price of an hour or of a
 * quantity such as a kilometre, per date range.
 *
 *   $rate = $twinfield->rates()->get('CONSULT');
 *   echo $rate->code, ' ', $rate->name, ' (', $rate->type->value, ')', PHP_EOL;
 *   echo $rate->priceOn(new DateTimeImmutable('2026-03-15'))?->external, ' ', $rate->currency, PHP_EOL;
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/rates/
 */
final readonly class Rate
{
    /** @var non-empty-list<RatePrice> Sorted by start date, oldest first. */
    public array $prices;

    /**
     * @param list<RatePrice> $prices In any order; at least one, without overlapping dates.
     */
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        public string $code,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
        public RateStatus $status,
        /** True when activities, projects or timesheets use the rate. Deleting it then hides it. */
        public bool $inUse,
        public RateType $type,
        /** Number of units the price is for, as set in Twinfield, e.g. `1` for a price per hour or kilometre. */
        public int $unit,
        /** Currency code, e.g. `EUR`. */
        public string $currency,
        public DateTimeImmutable $created,
        public DateTimeImmutable $modified,
        /** Number of times the rate was changed. */
        public int $touched,
        /** Code of the user who changed the rate last. */
        public string $modifiedBy,
        array $prices,
    ) {
        $required = [
            'code' => $code,
            'name' => $name,
            'currency' => $currency,
            'modifiedBy' => $modifiedBy,
        ];

        foreach ($required as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Rate %s must not be empty.', $field));
            }
        }

        if ($unit < 1 || $touched < 0) {
            throw new InvalidArgumentException(sprintf(
                'Rate %s must have a unit of 1 or more and touched of 0 or more, got %d and %d.',
                $code,
                $unit,
                $touched,
            ));
        }

        usort($prices, static fn (RatePrice $a, RatePrice $b): int => $a->from <=> $b->from);

        if ($prices === []) {
            throw new InvalidArgumentException(sprintf('Rate %s must have at least one price.', $code));
        }

        $previous = null;

        foreach ($prices as $price) {
            if ($previous !== null && ($previous->until === null || $previous->until >= $price->from)) {
                throw new InvalidArgumentException(sprintf(
                    'Rate %s has overlapping prices from %s and %s.',
                    $code,
                    $previous->from->format('Y-m-d'),
                    $price->from->format('Y-m-d'),
                ));
            }

            $previous = $price;
        }

        $this->prices = $prices;
    }

    /**
     * From a `<projectrate>` element of a read response.
     *
     * @internal
     */
    public static function fromXml(XmlElement $rate, Organisation $organisation): self
    {
        $prices = array_map(RatePrice::fromXml(...), $rate->child('ratechanges')->list('ratechange'));

        // Twinfield requires at least one price, so a rate without prices is not expected.
        if ($prices === []) {
            throw new UnexpectedResponseException(sprintf('%s/ratechanges has no <ratechange>.', $rate->path));
        }

        try {
            return new self(
                company: new CompanyReference($organisation, $rate->string('office')),
                code: $rate->string('code'),
                name: $rate->string('name'),
                // Optional in Twinfield: empty when no short name is set.
                shortName: $rate->nullableString('shortname'),
                status: $rate->enumAttribute('status', RateStatus::class),
                inUse: $rate->boolAttribute('inuse'),
                type: $rate->enum('type', RateType::class),
                unit: $rate->int('unit'),
                currency: $rate->string('currency'),
                created: $rate->dateTime('created'),
                modified: $rate->dateTime('modified'),
                touched: $rate->int('touched'),
                modifiedBy: $rate->string('user'),
                prices: $prices,
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s: %s', $rate->path, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * The price that applies on the date, or null when no price covers it.
     */
    public function priceOn(DateTimeInterface $date): ?RatePrice
    {
        foreach ($this->prices as $price) {
            if ($price->appliesOn($date)) {
                return $price;
            }
        }

        return null;
    }

    /**
     * The price with the latest start date.
     */
    public function latestPrice(): RatePrice
    {
        return $this->prices[array_key_last($this->prices)];
    }

    /**
     * Input with all values and prices of this rate, to change and pass to update() or save().
     * The prices keep their line id, so the update changes them instead of adding them again.
     *
     *   $rates->update($rate->edit()->newPriceFrom(new DateTimeImmutable('2026-07-01'), '60', '105'));
     */
    public function edit(): RateInput
    {
        $input = RateInput::create($this->code, $this->name, $this->type)
            ->shortName($this->shortName)
            ->unit($this->unit)
            ->currency($this->currency);

        foreach ($this->prices as $price) {
            $input = $input->existingPrice($price);
        }

        return $input;
    }
}
