<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

/**
 * Type of an office (`general/type`). Twinfield does not document the values; these are the
 * ones seen in live responses of an accountancy firm's environment.
 */
enum OfficeType: string
{
    case AccountantInternal = 'accountantinternal';
    case AccountantExternal = 'accountantexternal';
}
