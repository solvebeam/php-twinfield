<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Transaction\PerformanceType;

/**
 * An article (item) of the Classic invoicing module: what a sales invoice line refers to, together
 * with one of its sub-articles. The article has the settings (VAT code, units, what may be changed
 * on the invoice), the sub-articles have the prices and the revenue accounts.
 *
 * These are not the sales articles of the REST API (`salesArticles()`): those are a separate list.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/articles/
 */
final readonly class Article
{
    /**
     * @param list<SubArticle> $subArticles In the order of Twinfield, deleted ones included.
     */
    public function __construct(
        public CompanyReference $company,
        public string $code,
        public ArticleStatus $status,
        public ArticleType $type,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
        /** Name of one unit, e.g. `Piece`; null when not set. */
        public ?string $unitNameSingular,
        /** Name of several units, e.g. `Pieces`; null when not set. */
        public ?string $unitNamePlural,
        /** Default VAT code; null when not set, e.g. for a discount. */
        public ?string $vatCode,
        /** The VAT code may be changed on the invoice line. */
        public bool $allowChangeVatCode,
        /** Performance type for ICT VAT codes; null when not set. */
        public ?PerformanceType $performanceType,
        /**
         * The performance type may be changed on the invoice line; null when Twinfield leaves it out,
         * which it does for discounts and premiums.
         */
        public ?bool $allowChangePerformanceType,
        /** The price of a discount or premium is a percentage instead of an amount. */
        public bool $percentage,
        /** A discount or premium may be applied to this article. */
        public bool $allowDiscountOrPremium,
        /** The unit price may be changed on the invoice line. */
        public bool $allowChangeUnitPrice,
        /** The quantity on the invoice line may have decimals. */
        public bool $allowDecimalQuantity,
        public array $subArticles,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('Article code must not be empty.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Article name must not be empty.');
        }

        if ($subArticles === []) {
            throw new InvalidArgumentException(sprintf('Article %s needs at least one sub-article.', $code));
        }
    }

    /**
     * The sub-article with this code, or null when the article has none with it. Pass null for the
     * sub-article without code.
     */
    public function subArticle(?string $code): ?SubArticle
    {
        foreach ($this->subArticles as $subArticle) {
            if ($subArticle->code === $code) {
                return $subArticle;
            }
        }

        return null;
    }

    /**
     * The sub-articles that are not deleted.
     *
     * @return list<SubArticle>
     */
    public function activeSubArticles(): array
    {
        return array_values(array_filter(
            $this->subArticles,
            static fn (SubArticle $subArticle): bool => $subArticle->status === SubArticleStatus::Active,
        ));
    }

    /**
     * Input to change this article, with its current values and sub-articles:
     * `articles()->update($article->edit()->name('…'))`.
     */
    public function edit(): ArticleInput
    {
        return ArticleInput::existing($this);
    }
}
