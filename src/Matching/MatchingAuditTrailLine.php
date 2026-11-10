<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A transaction line in a matching audit trail event: which line was matched, and for how much.
 */
final readonly class MatchingAuditTrailLine
{
    public function __construct(
        /** Day book (transaction type) code, e.g. `VRK`. */
        public string $dayBook,
        /** Transaction number, e.g. `202600007`. */
        public int $number,
        /** Line ID within the transaction. */
        public int $lineId,
        /** Matched amount in the base currency of the company. */
        public Amount $matchedAmount,
        /** Matched amount in the currency of the transaction. */
        public Amount $matchedAmountTransactionCurrency,
        /** Matched amount in the reporting currency; zero when the company has none. */
        public Amount $matchedAmountReportingCurrency,
    ) {
    }
}
