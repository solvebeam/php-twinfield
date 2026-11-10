<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * Search on the fixed asset classes, created by `fixedAssets()->classes()`.
 *
 * @extends RestSearch<FixedAssetClassSummary>
 */
final readonly class FixedAssetClassSearch extends RestSearch
{
    public function pattern(string $pattern): self
    {
        return $this->with('searchPattern', $pattern);
    }

    public function orderBy(string $field, bool $ascending = true): self
    {
        return $this->sort($field, $ascending);
    }

    protected function map(JsonValue $item, CompanyReference $company): FixedAssetClassSummary
    {
        return FixedAssetReader::classSummary($item, $company);
    }
}
