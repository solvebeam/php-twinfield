<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

/**
 * Type of an article (`article/header/type`): "special item" in the Twinfield interface.
 */
enum ArticleType: string
{
    /** An ordinary article: special item "none". */
    case Normal = 'normal';

    /** A deduction: its price is subtracted, as an amount or a percentage. */
    case Discount = 'discount';

    /** A premium: its price is added, as an amount or a percentage. */
    case Premium = 'premium';
}
