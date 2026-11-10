<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

/**
 * Maps a PHP identifier (class or property) to its corresponding XML element name.
 *
 * Usage on classes:
 *   #[SoapName('Search')]
 *
 * Usage on properties:
 *   #[SoapName('AccessToken')]
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class SoapName
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
