<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

/**
 * Side of a transaction line. Amounts are always positive; this gives the direction.
 */
enum DebitCredit: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function opposite(): self
    {
        return match ($this) {
            self::Debit => self::Credit,
            self::Credit => self::Debit,
        };
    }
}
