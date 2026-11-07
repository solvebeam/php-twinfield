<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Currency;

/**
 * Status of a currency (`currency/@status`).
 */
enum CurrencyStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';
}
