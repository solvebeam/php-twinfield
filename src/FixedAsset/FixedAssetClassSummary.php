<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A fixed asset class in a list (`fixedAssets()->classes()`). The list has no code: `getClass()` reads
 * it with the code, accounts and the depreciation per regime.
 */
final readonly class FixedAssetClassSummary
{
    public function __construct(
        public CompanyReference $company,
        /** GUID of the class. */
        public string $id,
        public string $name,
        /** Default depreciation method. */
        public DepreciationMethod $method,
        public LifetimeType $lifetimeType,
        /** @var numeric-string */
        public string $lifetime,
        /** True when assets use the class. */
        public bool $used,
        public int $version,
    ) {
        Guid::of($id);
    }
}
