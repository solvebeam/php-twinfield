<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TransactionBlockedValue;

/**
 * Error codes of the TransactionBlockedValueService, in CommandQueryFaultException::$errorCode
 * (type `TransactionBlockedValueServiceFault`).
 *
 *   try {
 *       $twinfield->transactionBlockedValues()->register('INK', 202600021, 1, '2000.00');
 *   } catch (CommandQueryFaultException $exception) {
 *       $code = TransactionBlockedValueServiceFaultCode::tryFrom($exception->errorCode);
 *
 *       if ($code === TransactionBlockedValueServiceFaultCode::BlockedValueCannotBeRegistered) { … }
 *   }
 */
enum TransactionBlockedValueServiceFaultCode: string
{
    /** Twinfield refused to register the blocked value; the message says why. */
    case BlockedValueCannotBeRegistered = 'BlockedValueCannotBeRegistered';

    /** Twinfield refused to remove the blocked value; the message says why. */
    case BlockedValueCannotBeUnregistered = 'BlockedValueCannotBeUnregistered';
}
