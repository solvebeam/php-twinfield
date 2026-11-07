<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

/**
 * Payment status of a transaction line (`matchstatus`).
 */
enum MatchStatus: string
{
    /** Open: can still be matched, e.g. an unpaid invoice. */
    case Available = 'available';
    case Matched = 'matched';
    case Proposed = 'proposed';
    /** Lines on an account that is not matched, such as the bank account or a profit and loss account. */
    case NotMatchable = 'notmatchable';
}
