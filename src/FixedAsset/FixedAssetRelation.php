<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * How a fixed asset came from or led to another one.
 */
enum FixedAssetRelation: string
{
    case Split = 'split';
    case Merge = 'merge';
}
