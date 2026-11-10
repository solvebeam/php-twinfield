<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * A transaction an action of a fixed asset posted, e.g. a depreciation.
 */
final readonly class FixedAssetTransaction
{
    public function __construct(
        /** Day book (transaction type) code, e.g. `ACTIVA`. */
        public string $dayBook,
        /** Transaction number, e.g. `202300016`. */
        public int $number,
    ) {
    }
}
