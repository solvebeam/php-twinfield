<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

use InvalidArgumentException;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A sub-article of an article: the variant an invoice line refers to, with its own price and
 * revenue account. Every article has at least one.
 */
final readonly class SubArticle
{
    public function __construct(
        /** Code of the sub-article; null for the only sub-article of an article, which may have no code. */
        public ?string $code,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
        /** The number of units of the article per quantity. */
        public int $units,
        /** Price excluding VAT; for a discount or premium with a percentage, the percentage. */
        public Amount $unitPriceExcl,
        /** Price including VAT; for a discount or premium with a percentage, the percentage. */
        public Amount $unitPriceInc,
        /** Code of the general ledger account the revenue is booked on. */
        public string $generalLedgerAccount,
        /** Name of the general ledger account; null when Twinfield gives none. */
        public ?string $generalLedgerAccountName,
        /** Code of the cost center; null when not set. */
        public ?string $costCenter,
        /** Name of the cost center; null without cost center, or when Twinfield gives none. */
        public ?string $costCenterName,
        /** Free text of the sub-article; null when not set. */
        public ?string $freeText3,
        public SubArticleStatus $status,
        /** The sub-article is used in an invoice: Twinfield keeps it when you delete it. */
        public bool $inUse,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('Sub-article code must not be empty: use null for no code.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Sub-article name must not be empty.');
        }

        if ($generalLedgerAccount === '') {
            throw new InvalidArgumentException('Sub-article general ledger account must not be empty.');
        }
    }

    /**
     * Input with the current values of this sub-article, for `ArticleInput::subArticle()`.
     */
    public function edit(): SubArticleInput
    {
        return SubArticleInput::existing($this);
    }
}
