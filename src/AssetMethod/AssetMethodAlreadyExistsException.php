<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\AssetMethod;

use RuntimeException;

/**
 * create() was called for a code that already exists. Use update() or save() to change it.
 */
final class AssetMethodAlreadyExistsException extends RuntimeException
{
    public function __construct(
        public readonly string $assetMethodCode,
    ) {
        parent::__construct(sprintf('Asset method %s already exists.', $assetMethodCode));
    }
}
