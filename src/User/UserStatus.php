<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

/**
 * Status of a user (`user/@status`). Deleting a user that is used in transactions hides it
 * instead; activate() makes a deleted or hidden user active again.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';
    case Hidden = 'hide';
}
