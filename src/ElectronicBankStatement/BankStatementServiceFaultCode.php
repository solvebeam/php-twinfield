<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

/**
 * Error codes of the BankStatementService, in CommandQueryFaultException::$errorCode
 * (type `BankStatementServiceFault`).
 *
 *   try {
 *       $twinfield->electronicBankStatements()->list($from, $to);
 *   } catch (CommandQueryFaultException $exception) {
 *       $code = BankStatementServiceFaultCode::tryFrom($exception->errorCode);
 *   }
 */
enum BankStatementServiceFaultCode: string
{
    /** The method is disabled. In the WSDL, not in the documentation; not seen live yet. */
    case MethodIsDisabled = 'MethodIsDisabled';
}
