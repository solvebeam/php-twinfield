<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

/**
 * Which balance of a general ledger account a node of a reporting structure shows: the balance
 * (`Balance`), or only a debit or only a credit balance. An account with `Debit` is usually also
 * bound to another node with `Credit`, e.g. a bank account that can be overdrawn.
 */
enum HierarchyAccountBalanceType: string
{
    case Balance = 'Balance';
    case Debit = 'Debit';
    case Credit = 'Credit';
}
