<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PostingRule;

/**
 * A line of a posting rule: where (a share of) the amount is posted. All references are GUIDs.
 */
final readonly class PostingRuleLine
{
    public function __construct(
        /**
         * GUID of the general ledger account. Null on a line that only sets a VAT code: Twinfield has
         * such lines (2 of 258 lines in a test company).
         */
        public ?string $generalLedgerAccountId,
        /** GUID of the company for intercompany posting; null for the own company. */
        public ?string $destinationCompanyId,
        public ?string $costCenterId,
        public ?string $projectId,
        public ?string $vatCodeId,
        /**
         * Share of this line, e.g. `50.7`; null when the rule has one line or a fixed amount.
         *
         * @var ?numeric-string
         */
        public ?string $ratio,
        public ?string $description,
    ) {
    }
}
