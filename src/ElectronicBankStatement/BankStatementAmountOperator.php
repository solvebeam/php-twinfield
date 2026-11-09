<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

/**
 * How an allocation rule compares the amount of a line (`BankStatementAmountOperator`).
 */
enum BankStatementAmountOperator: string
{
    case Equal = 'Equal';
    case NotEqual = 'NotEqual';
    case LessThan = 'LessThan';
    case LessThanOrEqualTo = 'LessThanOrEqualTo';
    case MoreThan = 'MoreThan';
    case MoreThanOrEqualTo = 'MoreThanOrEqualTo';
}
