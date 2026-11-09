<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

/**
 * How Twinfield recognised a bank statement line for allocation (`BankStatementAllocationRecognition`).
 */
enum BankStatementAllocationRecognition: string
{
    /** Recognised by an allocation rule. */
    case AllocationRule = 'AllocationRule';

    /** Recognised by the account number (BBAN) of the other party. */
    case ContraAccountNumber = 'ContraAccountNumber';

    /** Recognised by the IBAN of the other party. */
    case ContraIban = 'ContraIban';

    /** Recognised by an invoice number. */
    case InvoiceNumber = 'InvoiceNumber';

    /** Recognised by a customer or supplier code. */
    case RelationCode = 'RelationCode';

    /** Recognised by a cheque number. */
    case ChequeNumber = 'ChequeNumber';

    /** Recognised by a paying-in slip number. */
    case PayingInSlipNumber = 'PayingInSlipNumber';

    /** Recognised by a reject, refusal or reversal message. */
    case PaymentResult = 'PaymentResult';
}
