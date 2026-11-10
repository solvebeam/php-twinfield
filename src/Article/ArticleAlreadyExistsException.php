<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

use RuntimeException;

/**
 * create() was called for a code that already exists. Use update() or save() to change it.
 */
final class ArticleAlreadyExistsException extends RuntimeException
{
    public function __construct(
        public readonly string $articleCode,
    ) {
        parent::__construct(sprintf('Article %s already exists.', $articleCode));
    }
}
