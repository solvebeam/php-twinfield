<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * The fixed asset groups, created by `fixedAssets()->groups()`.
 *
 * @extends RestSearch<FixedAssetGroupSummary>
 */
final readonly class FixedAssetGroupSearch extends RestSearch
{
    protected function map(JsonValue $item, CompanyReference $company): FixedAssetGroupSummary
    {
        return FixedAssetReader::groupSummary($item, $company);
    }
}
