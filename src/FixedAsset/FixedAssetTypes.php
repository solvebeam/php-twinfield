<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * The asset types (up to three levels) of a fixed asset from a year on, by GUID.
 */
final readonly class FixedAssetTypes
{
    public function __construct(
        public int $year,
        public string $type1Id,
        public ?string $type2Id,
        public bool $type2Required,
        public ?string $type3Id,
        public bool $type3Required,
    ) {
    }
}
