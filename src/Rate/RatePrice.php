<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rate;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * The prices of a rate in a date range (a `<ratechange>` line), as Twinfield returns it.
 *
 *   $price = $rate->priceOn(new DateTimeImmutable('2026-03-15'));
 *   echo $price?->external, ' per unit', PHP_EOL;
 */
final readonly class RatePrice
{
    /**
     * End date Twinfield uses for a price without end date.
     *
     * @internal
     */
    public const string OPEN_END = '29991231';

    public function __construct(
        /** Line id, unique within the rate. edit() sends it, so an update changes this line. */
        public int $id,
        public RateStatus $status,
        /** First day the price applies; the time is always 00:00. */
        public DateTimeImmutable $from,
        /** Last day the price applies; null when it has no end (Twinfield: 2999-12-31). */
        public ?DateTimeImmutable $until,
        /** Internal rate: the cost price per unit. */
        public UnitPrice $internal,
        /** External rate: the selling price per unit. */
        public UnitPrice $external,
    ) {
        if ($until !== null && $until->format('Ymd') < $from->format('Ymd')) {
            throw new InvalidArgumentException(sprintf(
                'Rate price %d ends on %s, before it starts on %s.',
                $id,
                $until->format('Y-m-d'),
                $from->format('Y-m-d'),
            ));
        }
    }

    /**
     * From a `<ratechange>` element of `<projectrate><ratechanges>`. `@lastused` is obsolete
     * and not read.
     */
    public static function fromXml(XmlElement $line): self
    {
        $until = $line->string('enddate');
        $id = $line->intAttribute('id');
        $status = $line->enumAttribute('status', RateStatus::class);
        $from = self::date($line, 'begindate');
        $until = $until === self::OPEN_END ? null : self::date($line, 'enddate');
        $internal = self::price($line, 'internalrate');
        $external = self::price($line, 'externalrate');

        try {
            return new self($id, $status, $from, $until, $internal, $external);
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s: %s', $line->path, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * Whether the price applies on this date.
     */
    public function appliesOn(DateTimeInterface $date): bool
    {
        $day = $date->format('Ymd');

        return $this->from->format('Ymd') <= $day && ($this->until === null || $day <= $this->until->format('Ymd'));
    }

    private static function date(XmlElement $line, string $name): DateTimeImmutable
    {
        try {
            return XmlValue::toDate($line->string($name));
        } catch (InvalidArgumentException) {
            throw $line->child($name)->invalid('a date yyyymmdd');
        }
    }

    private static function price(XmlElement $line, string $name): UnitPrice
    {
        try {
            return UnitPrice::fromString($line->string($name));
        } catch (InvalidArgumentException) {
            throw $line->child($name)->invalid('a price of 0 or more with at most 4 decimals, e.g. 0.1500');
        }
    }
}
