<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

/**
 * Status of a sub-article (`article/lines/line/@status`).
 */
enum SubArticleStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';
}
