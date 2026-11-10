<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesArticle;

use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rate\UnitPrice;

/**
 * A sales article (REST API, Sales Articles): a product or service with a price, VAT code and revenue
 * account, used on sales invoices. Prices have four decimals.
 */
final readonly class SalesArticle
{
    /**
     * @param array<string, string> $translations Name per culture, e.g. `['en-GB' => 'Bike']`.
     */
    public function __construct(
        public CompanyReference $company,
        /** GUID of the article. */
        public string $id,
        public string $name,
        /** Code; null when the article has none (Twinfield allows that). */
        public ?string $code,
        /** Price without VAT. */
        public UnitPrice $netPrice,
        /** Price with VAT. */
        public UnitPrice $grossPrice,
        /** GUID of the VAT code: `vatCodes()->getDetails($article->vatCodeId)`. */
        public string $vatCodeId,
        /** GUID of the revenue type (revenue account). */
        public string $revenueTypeId,
        /** GUID of the cost center; null when not set. */
        public ?string $costCenterId,
        public array $translations,
        /** Version for optimistic locking: update() sends it, and fails when the article changed. */
        public int $version,
    ) {
        Guid::of($id);

        if ($name === '') {
            throw new InvalidArgumentException('Sales article name must not be empty.');
        }
    }

    /**
     * Input to change this article, with its current values and version:
     * `salesArticles()->update($article->edit()->name('…'))`.
     */
    public function edit(): SalesArticleInput
    {
        return SalesArticleInput::create($this->name, $this->vatCodeId)
            ->withIdentity($this->id, $this->version)
            ->code($this->code)
            ->netPrice($this->netPrice)
            ->revenueType($this->revenueTypeId)
            ->costCenter($this->costCenterId)
            ->translations($this->translations);
    }
}
