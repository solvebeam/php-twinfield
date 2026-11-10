<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

/**
 * Side of the budget lines an amount is spread over.
 */
enum SpreadFor: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
