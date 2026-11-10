<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Period;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use Stringable;

/**
 * Year and period, e.g. `2026/11`, as in transactions (`<period>`) and browse data
 * (`fin.trs.head.yearperiod`).
 *
 *   Period::of(2026, 11);
 *   Period::fromString('2026/11');
 *
 * The name, end date and whether the period is open come from the PeriodService:
 * `$twinfield->periods()->get(Period::of(2026, 11))` returns PeriodDetails.
 */
final readonly class Period implements Stringable
{
    private function __construct(
        public int $year,
        /** 1–12 for a year with 12 periods; 0 is the opening balance, 55 the preceding journal entries. */
        public int $number,
    ) {
    }

    public static function of(int $year, int $number): self
    {
        // Validates the range.
        XmlValue::period($year, $number);

        return new self($year, $number);
    }

    /**
     * @throws InvalidArgumentException When the value is not `yyyy/p` or `yyyy/pp`.
     */
    public static function fromString(string $period): self
    {
        $parsed = XmlValue::toPeriod($period);

        return self::of($parsed['year'], $parsed['number']);
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->number === $other->number;
    }

    /**
     * `yyyy/pp`, as Twinfield expects it.
     */
    public function __toString(): string
    {
        return XmlValue::period($this->year, $this->number);
    }
}
