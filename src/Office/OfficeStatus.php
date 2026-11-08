<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

/**
 * Status of an office (`office/@status`). Live responses only have `active`; the read and list
 * only return offices the user has access to.
 */
enum OfficeStatus: string
{
    case Active = 'active';
}
