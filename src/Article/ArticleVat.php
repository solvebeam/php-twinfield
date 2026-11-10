<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

/**
 * Finder option `vat` of an article search: articles with prices including or excluding VAT.
 */
enum ArticleVat: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';
}
