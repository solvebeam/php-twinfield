<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

/**
 * Marks a class as a SOAP header element.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class SoapHeader
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $element,
    ) {}
}
