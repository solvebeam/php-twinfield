<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

/**
 * What an allocation rule looks at (`BankStatementAllocationMethodType`).
 */
enum BankStatementAllocationMethodType: string
{
    /** The description of the line; the text is in BankStatementAllocationMethod::$text. */
    case Description = 'Description';

    /** The account number (BBAN) of the other party. */
    case ContraAccountNumber = 'ContraAccountNumber';

    /** The IBAN of the other party. */
    case ContraIban = 'ContraIban';

    /** The account name of the other party. */
    case ContraAccountName = 'ContraAccountName';

    /** The transaction type code of the bank, e.g. `N541`. */
    case TransactionTypeId = 'TransactionTypeId';

    /** The reference of the bank; the text is in BankStatementAllocationMethod::$text. */
    case Reference = 'Reference';

    /** The amount; see BankStatementAllocationMethod::$amountOperator and $amount. */
    case Amount = 'Amount';
}
