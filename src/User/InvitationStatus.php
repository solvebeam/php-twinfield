<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

/**
 * Status of the invitation to a Wolters Kluwer account, with which the user logs in
 * (`user/invitationstatus`).
 */
enum InvitationStatus: string
{
    case NotInvited = 'NotInvited';

    /** Invited, but the user has not activated the account yet. */
    case Invited = 'Invited';

    /** The user logs in with an active Wolters Kluwer account. */
    case Active = 'Active';
}
