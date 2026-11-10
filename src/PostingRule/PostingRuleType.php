<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PostingRule;

enum PostingRuleType: string
{
    /** Posting rule of a customer. */
    case Sales = 'sales';
    /** Posting rule of a supplier. */
    case Purchase = 'purchase';
}
