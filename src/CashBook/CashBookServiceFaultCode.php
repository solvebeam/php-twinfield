<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashBook;

/**
 * Error codes of the CashBookService, in CommandQueryFaultException::$errorCode (type `CashBookServiceFault`).
 *
 * From the WSDL; the documentation page lists other codes (`CashBookInvalidStructure`,
 * `CashBookInvalidBusinessLogic`) that the service does not use.
 *
 *   try {
 *       $twinfield->cashBooks()->changeGeneralLedgerAccount('KAS', '1010');
 *   } catch (CommandQueryFaultException $exception) {
 *       $code = CashBookServiceFaultCode::tryFrom($exception->errorCode);
 *
 *       if ($code === CashBookServiceFaultCode::GeneralLedgerAccountMayNotBeChanged) { … }
 *   }
 *
 * NotFound and AlreadyExists are thrown as CashBookNotFoundException and CashBookAlreadyExistsException.
 */
enum CashBookServiceFaultCode: string
{
    case NotFound = 'CashBookNotFound';
    case AlreadyExists = 'CashBookAlreadyExists';
    case InvalidGeneralLedgerAccount = 'CashBookInvalidGeneralLedgerAccount';
    case GeneralLedgerAccountMayNotBeChanged = 'CashBookGeneralLedgerAccountMayNotBeChanged';
}
