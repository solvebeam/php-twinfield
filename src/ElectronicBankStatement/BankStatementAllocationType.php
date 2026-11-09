<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

/**
 * What a bank statement line is allocated to (`BankStatementAllocationType`).
 */
enum BankStatementAllocationType: string
{
    /** The suspense account: Twinfield could not allocate the line. */
    case Suspense = 'Suspense';

    /** A general ledger account. */
    case GeneralLedger = 'GeneralLedger';

    case Customer = 'Customer';

    case Supplier = 'Supplier';

    case SupplierCheque = 'SupplierCheque';

    case PayingInSlip = 'PayingInSlip';
}
