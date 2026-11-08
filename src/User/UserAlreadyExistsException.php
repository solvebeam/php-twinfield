<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use RuntimeException;

/**
 * create() was called for a code that already exists. Use update() or save() to change it.
 */
final class UserAlreadyExistsException extends RuntimeException
{
    public function __construct(
        public readonly string $userCode,
    ) {
        parent::__construct(sprintf('User %s already exists.', $userCode));
    }
}
