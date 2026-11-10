<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SpreadTransaction;

use InvalidArgumentException;
use SolveBeam\Twinfield\Period\Period;

/**
 * How a sales or purchase transaction is spread: the intermediate account and the periods, either
 * the same for the whole transaction or per detail line.
 *
 *   SpreadSettings::forTransaction('2500', Period::of(2026, 1), Period::of(2026, 12));
 *   SpreadSettings::byLines('2500', new SpreadLine(2, Period::of(2026, 1), Period::of(2026, 6)));
 */
final readonly class SpreadSettings
{
    /**
     * @param list<SpreadLine> $lines
     */
    private function __construct(
        /**
         * The intermediate account (`dim1` of the settings): the balance sheet account the amounts
         * are moved to in the period of the transaction, and from which they are spread.
         */
        public string $intermediateAccount,
        /** First period of the spread; null when the periods are set per line. */
        public ?Period $startPeriod,
        /** Last period of the spread; null when the periods are set per line. */
        public ?Period $endPeriod,
        /** The periods per detail line; empty when they are the same for the whole transaction. */
        public array $lines,
    ) {
        if ($intermediateAccount === '') {
            throw new InvalidArgumentException('Intermediate account must not be empty.');
        }
    }

    /**
     * The same periods for all detail lines of the transaction.
     */
    public static function forTransaction(string $intermediateAccount, Period $startPeriod, Period $endPeriod): self
    {
        if ($endPeriod->isBefore($startPeriod)) {
            throw new InvalidArgumentException(sprintf(
                'End period %s is before start period %s.',
                $endPeriod,
                $startPeriod,
            ));
        }

        return new self($intermediateAccount, $startPeriod, $endPeriod, []);
    }

    /**
     * Periods per detail line; lines that are left out are not spread.
     */
    public static function byLines(string $intermediateAccount, SpreadLine ...$lines): self
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Spread settings by lines need at least one line.');
        }

        $ids = array_map(static fn (SpreadLine $line): int => $line->lineId, $lines);

        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('Spread settings by lines have the same line id more than once.');
        }

        return new self($intermediateAccount, null, null, array_values($lines));
    }

    /**
     * True when the periods are set per detail line, see `$lines`.
     */
    public function isByLines(): bool
    {
        return $this->lines !== [];
    }
}
