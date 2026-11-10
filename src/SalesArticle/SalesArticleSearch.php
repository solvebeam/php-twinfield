<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesArticle;

use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rate\UnitPrice;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * Search on the sales articles, created by `salesArticles()->search()`.
 *
 * @extends RestSearch<SalesArticle>
 */
final readonly class SalesArticleSearch extends RestSearch
{
    /**
     * Search pattern on code and name, e.g. `Bike*`.
     */
    public function pattern(string $pattern): self
    {
        return $this->with('searchPattern', $pattern);
    }

    public function scope(SearchScope $scope): self
    {
        return $this->with('searchScope', $scope->value);
    }

    /**
     * Match and sort on the names in this culture, e.g. `nl-NL`.
     */
    public function inCulture(string $culture): self
    {
        return $this->with('customerCultureCode', $culture);
    }

    /**
     * Only articles with one of these VAT codes (GUIDs).
     */
    public function vatCodes(string ...$vatCodeIds): self
    {
        $ids = array_values(array_map(static fn (string $id): string => Guid::of($id, 'VAT code ID'), $vatCodeIds));

        return $this->with('vats', $ids === [] ? null : $ids);
    }

    /**
     * @param UnitPrice|numeric-string|null $from
     * @param UnitPrice|numeric-string|null $to
     */
    public function grossPriceBetween(UnitPrice|string|null $from, UnitPrice|string|null $to): self
    {
        return $this->with('grossPriceFrom', $from === null ? null : UnitPrice::of($from)->value)
            ->with('grossPriceTo', $to === null ? null : UnitPrice::of($to)->value);
    }

    /**
     * Sort by a field, e.g. `name`, `code` or `netPrice`.
     */
    public function orderBy(string $field, bool $ascending = true): self
    {
        return $this->sort($field, $ascending);
    }

    protected function map(JsonValue $item, CompanyReference $company): SalesArticle
    {
        return SalesArticleResource::read($item, $company);
    }
}
