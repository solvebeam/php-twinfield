<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod;

/**
 * Type of a free text field on fixed assets (`freetext/@type`).
 */
enum FreeTextType: string
{
    case Text = 'text';
    case Amount = 'amount';
}
