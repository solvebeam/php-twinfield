<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A fixed asset group: assets that are depreciated together, with a stop value per year and regime.
 */
final readonly class FixedAssetGroup
{
    /**
     * @param array<int, array<string, Amount>> $stopValues Stop value per year and Regime value,
     *                                                      e.g. `$group->stopValues[2026]['fiscal']`.
     */
    public function __construct(
        public CompanyReference $company,
        /** GUID of the group. */
        public string $id,
        public string $name,
        public int $startYear,
        public array $stopValues,
        public int $version,
    ) {
        Guid::of($id);
    }
}
