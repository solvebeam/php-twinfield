<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

/**
 * Single sign-on of a user (`user/account/sso`). Only available when single sign-on is enabled
 * for the organisation.
 */
enum SingleSignOn: string
{
    case Disabled = '0';
    case Enabled = '1';

    /** The user can only log in with single sign-on. */
    case Required = '2';
}
