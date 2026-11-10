<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * Another fixed asset this one was split or merged from (parent) or into (child).
 */
final readonly class FixedAssetLink
{
    public function __construct(
        /** GUID of the other asset. */
        public string $id,
        public string $description,
        public FixedAssetRelation $relation,
    ) {
    }
}
