<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\AssetMethod;

/**
 * Status of an asset method (`assetmethod/@status`).
 */
enum AssetMethodStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';

    /** Twinfield hides an asset method instead of deleting it when it is in use. */
    case Hidden = 'hide';
}
