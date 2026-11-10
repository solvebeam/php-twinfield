<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\LedgerAccount;

/**
 * Whether transaction lines on the account need a dimension 2 (customer, supplier or cost center).
 */
enum Subanalysis: string
{
    case Forbidden = 'forbidden';
    case Allowed = 'allowed';
    case Mandatory = 'mandatory';
}
