<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankBook;

/**
 * Sign-up state of automatic bank processing (bank statements, payments and direct debits via the bank).
 */
enum AutomaticBankProcessingSignUpState: string
{
    case Inactive = 'Inactive';
    case Pending = 'Pending';
    case Active = 'Active';
}
