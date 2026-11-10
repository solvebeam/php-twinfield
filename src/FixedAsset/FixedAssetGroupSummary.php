<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A fixed asset group in a list (`fixedAssets()->groups()`).
 */
final readonly class FixedAssetGroupSummary
{
    public function __construct(
        public CompanyReference $company,
        /** GUID of the group. */
        public string $id,
        public string $name,
    ) {
        Guid::of($id);
    }
}
