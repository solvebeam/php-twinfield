<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DeletedTransaction;

/**
 * Error codes of the DeletedTransactionsService, in CommandQueryFaultException::$errorCode
 * (type `DeletedTransactionsServiceFault`).
 *
 *   try {
 *       $twinfield->deletedTransactions()->list(dayBook: 'XXXX');
 *   } catch (CommandQueryFaultException $exception) {
 *       $code = DeletedTransactionsServiceFaultCode::tryFrom($exception->errorCode);
 *
 *       if ($code === DeletedTransactionsServiceFaultCode::InvalidDaybook) { … }
 *   }
 */
enum DeletedTransactionsServiceFaultCode: string
{
    /** The company does not exist (`De kantoorcode bestaat niet.`). */
    case InvalidCompanyCode = 'InvalidCompanyCode';

    /** The day book does not exist (`Dagboek XXXX bestaat niet.`). */
    case InvalidDaybook = 'InvalidDaybook';

    /** The from date is after the to date. list() checks this before sending. */
    case InvalidDates = 'InvalidDates';
}
