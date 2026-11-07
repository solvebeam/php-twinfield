<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

/**
 * Reporting regime of a budget query (`RegimeType`).
 *
 * Not in the Twinfield documentation, only in the WSDL of the BudgetService. It is meant for
 * companies that report under more than one regime (e.g. fiscal and commercial). Without a
 * regime Twinfield uses the default of the company.
 */
enum Regime: string
{
    case Generic = 'Generic';
    case Fiscal = 'Fiscal';
    case Commercial = 'Commercial';
    case Economic = 'Economic';
}
