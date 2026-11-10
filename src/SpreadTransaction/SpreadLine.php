<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SpreadTransaction;

use InvalidArgumentException;
use SolveBeam\Twinfield\Period\Period;

/**
 * The spread of one detail line of a sales or purchase transaction: the line and the periods its
 * amount is spread over.
 *
 *   new SpreadLine(2, Period::of(2026, 1), Period::of(2026, 12));
 *
 * The line id is `DetailLine::$id` of the transaction, e.g. from
 * `$twinfield->purchaseTransactions()->get('INK', 202600001)->lines->details`.
 */
final readonly class SpreadLine
{
    public function __construct(
        /** Id of the detail line within the transaction, from 1. */
        public int $lineId,
        /** First period of the spread. */
        public Period $startPeriod,
        /** Last period of the spread. */
        public Period $endPeriod,
    ) {
        if ($lineId < 1) {
            throw new InvalidArgumentException(sprintf('Line id must be 1 or more, got %d.', $lineId));
        }

        if ($endPeriod->isBefore($startPeriod)) {
            throw new InvalidArgumentException(sprintf(
                'End period %s of line %d is before start period %s.',
                $endPeriod,
                $lineId,
                $startPeriod,
            ));
        }
    }
}
