<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

/**
 * Type of a user (`user/type`). Twinfield checks it against the type of the organisation.
 */
enum UserType: string
{
    /** User of an accountancy organisation. */
    case Accountant = 'accountant';

    /** Client of an accountant, working in the accountant's organisation. */
    case AccountantCustomer = 'accountantcustomer';

    /** User of an organisation that is not an accountancy. */
    case Regular = 'regular';
}
