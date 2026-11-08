<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

/**
 * Language in which Twinfield is shown to the user (`user/culture`).
 */
enum UserCulture: string
{
    case Czech = 'cs-CZ';
    case Danish = 'da-DK';
    case Dutch = 'nl-NL';
    case English = 'en-GB';
    case French = 'fr-FR';
    case German = 'de-DE';
    case Hungarian = 'hu-HU';
}
