<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use Stringable;

/**
 * Financial year and period of a transaction, e.g. `2026/11`.
 *
 *   FinancialPeriod::of(2026, 11);
 *   FinancialPeriod::fromString('2026/11');
 */
final readonly class FinancialPeriod implements Stringable
{
    private function __construct(
        public int $year,
        /** 1–12 for a year with 12 periods; 0 is the opening balance, 55 the year end. */
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

    /**
     * `yyyy/pp`, as Twinfield expects it.
     */
    public function __toString(): string
    {
        return XmlValue::period($this->year, $this->number);
    }
}
