<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

/**
 * Marks a class as a SOAP array type. The mapper collects child elements
 * matching `elementName` and maps each to `type`.
 *
 * - When `type` is a builtin like 'string', 'int', etc., the text content is coerced.
 * - When `type` is a class name, each child element is recursively mapped.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class SoapArray
{
    public function __construct(
        public string $elementName,
        public string $type = 'string',
    ) {
    }
}
