<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\GeneralLedgerAccount;

/**
 * Type of a VAT code (`vatcode/@type`).
 */
enum VatCodeType: string
{
    case Purchase = 'purchase';
    case Sales = 'sales';
}
