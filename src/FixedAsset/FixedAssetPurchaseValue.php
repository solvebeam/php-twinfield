<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Transaction\Amount;

/**
 * Part of the purchase value of a fixed asset, e.g. the purchase itself or an extra investment.
 */
final readonly class FixedAssetPurchaseValue
{
    public function __construct(
        public Regime $regime,
        public Amount $amount,
        /** Number of the period of the asset from which on it counts, e.g. `1`. */
        public int $fromPeriodNumber,
        /** Name of the origin in the language of the user, e.g. `Purchase` or `Inkoop`. */
        public string $origin,
    ) {
    }
}
