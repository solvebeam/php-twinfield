<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesArticle;

/**
 * A culture (language) sales articles can have a name in.
 */
final readonly class Culture
{
    public function __construct(
        /** E.g. `nl-NL`. */
        public string $code,
        /** Name of the culture in its own language, e.g. `Nederlands (Nederland)`. */
        public string $name,
    ) {
    }
}
