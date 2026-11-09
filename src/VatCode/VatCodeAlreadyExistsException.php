<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use RuntimeException;

/**
 * create() was called for a code that already exists. Use update() or save() to change it.
 */
final class VatCodeAlreadyExistsException extends RuntimeException
{
    public function __construct(
        public readonly string $vatCode,
    ) {
        parent::__construct(sprintf('VAT code %s already exists.', $vatCode));
    }
}
