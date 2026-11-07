<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

/**
 * Whether a transaction is provisional or final: `destiny` in a request, `location` in a response.
 *
 * Only provisional transactions can be changed, posted or deleted.
 */
enum TransactionStatus: string
{
    case Provisional = 'temporary';
    case Final = 'final';
}
