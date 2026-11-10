<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * Whether a fixed asset class can be used.
 */
enum ClassActivationStatus: string
{
    case ToBeActivated = 'toBeActivated';
    case Active = 'active';
}
