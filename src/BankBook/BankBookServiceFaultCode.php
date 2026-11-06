<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankBook;

/**
 * Error codes of the BankBookService, in CommandQueryFaultException::$errorCode (type `BankBookServiceFault`).
 *
 * From the WSDL; the documentation page lists other codes (`BankBookInvalidStructure`,
 * `BankBookInvalidBusinessLogic`) that the service does not use.
 *
 *   try {
 *       $twinfield->bankBooks()->changeGeneralLedgerAccount('ING', '1110');
 *   } catch (CommandQueryFaultException $exception) {
 *       $code = BankBookServiceFaultCode::tryFrom($exception->errorCode);
 *
 *       if ($code === BankBookServiceFaultCode::GeneralLedgerAccountMayNotBeChanged) { … }
 *   }
 *
 * NotFound and AlreadyExists are thrown as BankBookNotFoundException and BankBookAlreadyExistsException.
 */
enum BankBookServiceFaultCode: string
{
    case NotFound = 'BankBookNotFound';
    case AlreadyExists = 'BankBookAlreadyExists';
    case InvalidAccountSettings = 'BankBookInvalidAccountSettings';
    case InvalidGeneralLedgerAccount = 'BankBookInvalidGeneralLedgerAccount';
    case AccountNumberMayNotBeChanged = 'BankBookAccountNumberMayNotBeChanged';
    case GeneralLedgerAccountMayNotBeChanged = 'BankBookGeneralLedgerAccountMayNotBeChanged';
}
