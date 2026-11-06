<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Period;

/**
 * Error codes of the PeriodService, in SoapFaultException::$detailCode.
 *
 *   try {
 *       $twinfield->periods()->list(1901);
 *   } catch (SoapFaultException $exception) {
 *       $code = PeriodServiceFaultCode::tryFrom($exception->detailCode ?? '');
 *
 *       if ($code === PeriodServiceFaultCode::YearNotFound) { … }
 *   }
 */
enum PeriodServiceFaultCode: string
{
    case YearNotFound = 'YearNotFound';
    case PeriodNotFound = 'PeriodNotFound';
    case YearCanNotBeDeleted = 'YearCanNotBeDeleted';
    case InvalidNumberOfPeriods = 'InvalidNumberOfPeriods';
    case YearCanNotBeCreated = 'YearCanNotBeCreated';
    case EndDateCanNotBeChanged = 'EndDateCanNotBeChanged';
    case NameCanNotBeChanged = 'NameCanNotBeChanged';
    case PeriodCanNotBeOpened = 'PeriodCanNotBeOpened';
    case PeriodCanNotBeClosed = 'PeriodCanNotBeClosed';
}
