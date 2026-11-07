<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

/**
 * Error codes of the BudgetService, in CommandQueryFaultException::$errorCode (type `BudgetServiceFault`).
 *
 * Neither the documentation nor the WSDL lists these: they were observed in live responses.
 * The query builder already prevents all but `BudgetNotFound`, which is thrown as
 * BudgetNotFoundException.
 */
enum BudgetServiceFaultCode: string
{
    case NotFound = 'BudgetNotFound';
    case CodeMandatory = 'CodeMandatory';
    case YearMandatory = 'YearMandatory';
    case ChooseAtLeastOneTransactionStatus = 'ChooseAtLeastOneTransactionStatus';
}
