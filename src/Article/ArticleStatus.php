<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

/**
 * Status of an article (`article/header/@status`).
 */
enum ArticleStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';

    /** Twinfield hides an article instead of deleting it when it is used in an invoice. */
    case Hidden = 'hide';
}
