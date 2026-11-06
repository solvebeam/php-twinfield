<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

/**
 * Marks a request class with a SOAPAction header value.
 * Only used on request/body classes, not responses.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class SoapAction
{
    public function __construct(
        public readonly string $action,
    ) {
    }
}
