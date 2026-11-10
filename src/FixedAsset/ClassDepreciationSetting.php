<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * Default depreciation of a fixed asset class in one regime.
 */
final readonly class ClassDepreciationSetting
{
    public function __construct(
        public Regime $regime,
        public DepreciationMethod $method,
        public LifetimeType $lifetimeType,
        /** @var numeric-string */
        public string $lifetime,
    ) {
    }
}
