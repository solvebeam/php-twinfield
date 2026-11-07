<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

/**
 * Whether and how Twinfield sends payment reminders (`creditmanagement/sendreminder`).
 */
enum SendReminder: string
{
    case Yes = 'true';
    case ByEmail = 'email';
    case No = 'false';
}
