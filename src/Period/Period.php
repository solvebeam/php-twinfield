<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Period;

use InvalidArgumentException;
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

    /**
     * @throws InvalidArgumentException When the year is not 1000–9999 or the number not 0–99.
     */
    public static function of(int $year, int $number): self
    {
        if ($year < 1000 || $year > 9999 || $number < 0 || $number > 99) {
            throw new InvalidArgumentException(sprintf('%d/%d is not a period yyyy/pp.', $year, $number));
        }

        return new self($year, $number);
    }

    /**
     * @throws InvalidArgumentException When the value is not `yyyy/p` or `yyyy/pp`.
     */
    public static function fromString(string $period): self
    {
        if (preg_match('~^(\d{4})/(\d{1,2})$~', $period, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a period yyyy/p or yyyy/pp.', $period));
        }

        return self::of((int) $matches[1], (int) $matches[2]);
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->number === $other->number;
    }

    /**
     * `yyyy/pp`, e.g. `2026/03`, as Twinfield writes it.
     */
    public function __toString(): string
    {
        return sprintf('%04d/%02d', $this->year, $this->number);
    }
}
