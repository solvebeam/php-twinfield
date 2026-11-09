<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

/**
 * Reporting regime, for companies that report under more than one regime (e.g. fiscal and
 * commercial).
 *
 * Used by budget queries (`RegimeType`, only in the WSDL of the BudgetService; without a regime
 * Twinfield uses the default of the company) and journal transactions (`regime`; without one
 * Twinfield books `Generic`, which is visible in all regimes).
 */
enum Regime: string
{
    case Generic = 'Generic';
    case Fiscal = 'Fiscal';
    case Commercial = 'Commercial';
    case Economic = 'Economic';
}
