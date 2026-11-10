<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesArticle;

/**
 * Which names of a sales article the search pattern matches.
 */
enum SearchScope: string
{
    /** The name in any language. */
    case AnyTranslation = 'anyTranslation';
    /** The name in the culture of the search (`inCulture()`), or else the default name. */
    case FirstAvailableTranslation = 'firstAvailableTranslation';
}
