<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rate;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A rate to create or update. Immutable: every method returns a new input.
 *
 *   $input = RateInput::time('CONSULT', 'Consultancy')
 *       ->currency('EUR')
 *       ->price(new DateTimeImmutable('2026-01-01'), internal: '55', external: '95');
 *
 *   $input = RateInput::quantity('KM', 'Kilometres')
 *       ->price(new DateTimeImmutable('2026-01-01'), internal: '0.15', external: '0.23');
 *
 * For an existing rate, start from `$rate->edit()`: it has all current prices with their line
 * id, so the update changes those lines instead of adding them again.
 *
 *   $rates->update($rate->edit()->newPriceFrom(new DateTimeImmutable('2026-07-01'), '60', '105'));
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/rates/
 *
 * @phpstan-type PriceLine array{
 *     id: ?int,
 *     from: DateTimeImmutable,
 *     until: ?DateTimeImmutable,
 *     internal: UnitPrice,
 *     external: UnitPrice,
 * }
 * @phpstan-type SavedPriceLine array{
 *     id: int,
 *     from: DateTimeImmutable,
 *     until: ?DateTimeImmutable,
 *     internal: UnitPrice,
 *     external: UnitPrice,
 * }
 */
final readonly class RateInput
{
    /**
     * @param array<int, PriceLine> $prices
     *        Prices to save, by start date as int `yyyymmdd`.
     * @param array<int, SavedPriceLine> $removedPrices
     *        Existing prices to delete, by start date as int `yyyymmdd`.
     */
    private function __construct(
        public string $code,
        public string $name,
        public RateType $type,
        public ?string $shortName,
        public int $unit,
        public ?string $currency,
        private array $prices,
        private array $removedPrices,
    ) {
        XmlValue::code($code);

        if ($name === '') {
            throw new InvalidArgumentException('Rate name must not be empty.');
        }

        XmlValue::maxLength('rate name', $name, 40);

        if ($shortName !== null) {
            XmlValue::maxLength('rate short name', $shortName, 20);
        }

        if ($unit < 1) {
            throw new InvalidArgumentException(sprintf('Rate unit must be 1 or more, %d given.', $unit));
        }

        if ($currency !== null) {
            XmlValue::code($currency);
        }
    }

    /**
     * @param string $code Rate code: A-Z, 0-9, `_`, `-` or `+`, e.g. `CONSULT`.
     * @param string $name At most 40 characters.
     */
    public static function create(string $code, string $name, RateType $type): self
    {
        return new self($code, $name, $type, null, 1, null, [], []);
    }

    /**
     * A rate for hours, e.g. `RateInput::time('CONSULT', 'Consultancy')`.
     */
    public static function time(string $code, string $name): self
    {
        return self::create($code, $name, RateType::Time);
    }

    /**
     * A rate for quantities such as kilometres, e.g. `RateInput::quantity('KM', 'Kilometres')`.
     */
    public static function quantity(string $code, string $name): self
    {
        return self::create($code, $name, RateType::Quantity);
    }

    /**
     * At most 40 characters.
     */
    public function name(string $name): self
    {
        return $this->with(name: $name);
    }

    /**
     * At most 20 characters. Null or an empty string clears it.
     */
    public function shortName(?string $shortName): self
    {
        return new self(
            $this->code,
            $this->name,
            $this->type,
            $shortName === '' ? null : $shortName,
            $this->unit,
            $this->currency,
            $this->prices,
            $this->removedPrices,
        );
    }

    public function type(RateType $type): self
    {
        return $this->with(type: $type);
    }

    /**
     * Number of units the prices are for: `1` (default) for a price per hour or per kilometre,
     * `8` for a price per 8 hours.
     */
    public function unit(int $unit): self
    {
        return $this->with(unit: $unit);
    }

    /**
     * Currency code, e.g. `EUR`. Not sent when not set.
     */
    public function currency(string $currency): self
    {
        return $this->with(currency: $currency);
    }

    /**
     * Adds the prices from a start date, or changes the prices with the same start date.
     * Without end date the prices apply until a later end date is set.
     *
     *   ->price(new DateTimeImmutable('2026-01-01'), internal: '55', external: '95')
     *   ->price(new DateTimeImmutable('2025-01-01'), '50', '90', new DateTimeImmutable('2025-12-31'))
     *
     * @param UnitPrice|numeric-string $internal Internal rate: the cost price per unit.
     * @param UnitPrice|numeric-string $external External rate: the selling price per unit.
     *
     * @throws InvalidArgumentException When the dates overlap another price, or end before the start.
     */
    public function price(
        DateTimeInterface $from,
        UnitPrice|string $internal,
        UnitPrice|string $external,
        ?DateTimeInterface $until = null,
    ): self {
        $key = self::key($from);

        return $this->withPrice([
            'id' => $this->prices[$key]['id'] ?? $this->removedPrices[$key]['id'] ?? null,
            'from' => self::day($from),
            'until' => $until === null ? null : self::day($until),
            'internal' => UnitPrice::of($internal),
            'external' => UnitPrice::of($external),
        ]);
    }

    /**
     * New prices from a date on: the price without end date ends the day before, and the new
     * prices apply from the date without end date. The usual way to change a rate.
     *
     *   $rate->edit()->newPriceFrom(new DateTimeImmutable('2026-07-01'), internal: '60', external: '105');
     *
     * @param UnitPrice|numeric-string $internal Internal rate: the cost price per unit.
     * @param UnitPrice|numeric-string $external External rate: the selling price per unit.
     *
     * @throws InvalidArgumentException When the price without end date starts after the date.
     */
    public function newPriceFrom(DateTimeInterface $from, UnitPrice|string $internal, UnitPrice|string $external): self
    {
        $input = $this;
        $day = self::day($from);

        foreach ($this->prices as $key => $price) {
            if ($price['until'] !== null || $key === self::key($day)) {
                continue;
            }

            if ($price['from'] > $day) {
                throw new InvalidArgumentException(sprintf(
                    'The current price starts on %s, after %s: use price() to add an earlier price.',
                    $price['from']->format('Y-m-d'),
                    $day->format('Y-m-d'),
                ));
            }

            $input = $input->price($price['from'], $price['internal'], $price['external'], $day->modify('-1 day'));
        }

        return $input->price($day, $internal, $external);
    }

    /**
     * Deletes the prices with this start date when the rate is saved.
     *
     * @throws InvalidArgumentException When the input has no price with this start date.
     */
    public function removePrice(DateTimeInterface $from): self
    {
        $key = self::key($from);
        $price = $this->prices[$key] ?? throw new InvalidArgumentException(sprintf(
            'Rate %s has no price from %s.',
            $this->code,
            $from->format('Y-m-d'),
        ));

        $prices = $this->prices;
        $removedPrices = $this->removedPrices;
        unset($prices[$key]);

        // A price that is not saved yet has no id: leaving it out is enough.
        if ($price['id'] !== null) {
            $removedPrices[$key] = [...$price, 'id' => $price['id']];
        }

        return $this->with(prices: $prices, removedPrices: $removedPrices);
    }

    /**
     * Adds a price as Twinfield returned it, with its line id, so saving changes that line.
     * edit() uses this; add new prices with price() or newPriceFrom().
     */
    public function existingPrice(RatePrice $price): self
    {
        return $this->withPrice([
            'id' => $price->id,
            'from' => $price->from,
            'until' => $price->until,
            'internal' => $price->internal,
            'external' => $price->external,
        ]);
    }

    /**
     * The `<projectrate>` request to save this rate in a company.
     *
     * @throws LogicException When the input has no prices: Twinfield requires at least one.
     *
     * @internal
     */
    public function toXml(string $company): XmlBuilder
    {
        if ($this->prices === []) {
            throw new LogicException(sprintf('Rate %s needs at least one price: add one with price().', $this->code));
        }

        $xml = XmlBuilder::create('projectrate');
        $rate = $xml->root();

        $xml->add($rate, 'office', $company);
        $xml->add($rate, 'code', $this->code);
        $xml->add($rate, 'name', $this->name);
        $xml->add($rate, 'shortname', $this->shortName);
        $xml->add($rate, 'type', $this->type->value);
        $xml->add($rate, 'unit', (string) $this->unit);

        if ($this->currency !== null) {
            $xml->add($rate, 'currency', $this->currency);
        }

        $lines = $xml->add($rate, 'ratechanges');

        foreach (self::sorted($this->prices) as $price) {
            self::addLine($xml, $lines, $price, []);
        }

        foreach (self::sorted($this->removedPrices) as $price) {
            self::addLine($xml, $lines, $price, ['status' => RateStatus::Deleted->value]);
        }

        return $xml;
    }

    /**
     * @param PriceLine $price
     */
    private function withPrice(array $price): self
    {
        $key = self::key($price['from']);

        if ($price['until'] !== null && $price['until'] < $price['from']) {
            throw new InvalidArgumentException(sprintf(
                'Rate %s: a price from %s cannot end on %s, before it starts.',
                $this->code,
                $price['from']->format('Y-m-d'),
                $price['until']->format('Y-m-d'),
            ));
        }

        foreach ($this->prices as $otherKey => $other) {
            if ($otherKey !== $key && self::overlaps($price, $other)) {
                throw new InvalidArgumentException(sprintf(
                    'Rate %s: the price from %s overlaps the price from %s%s.',
                    $this->code,
                    $price['from']->format('Y-m-d'),
                    $other['from']->format('Y-m-d'),
                    $other['until'] === null ? ' (without end date; use newPriceFrom())' : '',
                ));
            }
        }

        $prices = $this->prices;
        $removedPrices = $this->removedPrices;
        $prices[$key] = $price;
        unset($removedPrices[$key]);

        return $this->with(prices: $prices, removedPrices: $removedPrices);
    }

    /**
     * @param array<int, PriceLine>|null $prices
     * @param array<int, SavedPriceLine>|null $removedPrices
     */
    private function with(
        ?string $name = null,
        ?RateType $type = null,
        ?int $unit = null,
        ?string $currency = null,
        ?array $prices = null,
        ?array $removedPrices = null,
    ): self {
        return new self(
            $this->code,
            $name ?? $this->name,
            $type ?? $this->type,
            $this->shortName,
            $unit ?? $this->unit,
            $currency ?? $this->currency,
            $prices ?? $this->prices,
            $removedPrices ?? $this->removedPrices,
        );
    }

    /**
     * @param PriceLine $price
     * @param array<string, string> $attributes
     */
    private static function addLine(XmlBuilder $xml, DOMElement $lines, array $price, array $attributes): void
    {
        if ($price['id'] !== null) {
            $attributes = ['id' => (string) $price['id'], ...$attributes];
        }

        $line = $xml->add($lines, 'ratechange', null, $attributes);
        $xml->add($line, 'begindate', XmlValue::date($price['from']));
        $xml->add($line, 'enddate', $price['until'] === null ? RatePrice::OPEN_END : XmlValue::date($price['until']));
        $xml->add($line, 'internalrate', $price['internal']->value);
        $xml->add($line, 'externalrate', $price['external']->value);
    }

    /**
     * @template T
     *
     * @param array<int, T> $prices
     *
     * @return array<int, T>
     */
    private static function sorted(array $prices): array
    {
        ksort($prices);

        return $prices;
    }

    /**
     * Whether two price lines share a day. A line without end date runs on indefinitely.
     *
     * @param PriceLine $a
     * @param PriceLine $b
     */
    private static function overlaps(array $a, array $b): bool
    {
        return ($b['until'] === null || $a['from'] <= $b['until'])
            && ($a['until'] === null || $b['from'] <= $a['until']);
    }

    private static function key(DateTimeInterface $date): int
    {
        return (int) XmlValue::date($date);
    }

    /**
     * The date at 00:00, so dates compare by day.
     */
    private static function day(DateTimeInterface $date): DateTimeImmutable
    {
        return XmlValue::toDate(XmlValue::date($date));
    }
}
