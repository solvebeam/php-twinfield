<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\GeneralLedgerAccount;

/**
 * Whether transaction lines on the account need a dimension 2 (and 3), e.g. a cost center or
 * customer (`financials/subanalyse`). Child validations say which dimensions are allowed.
 */
enum SubAnalysis: string
{
    /** Every line needs a dimension 2, e.g. a cost center. */
    case Required = 'true';

    /** Lines have no dimension 2. */
    case NotAllowed = 'false';

    /** A dimension 2 is allowed, not required. Usual for profit and loss accounts. */
    case Optional = 'maybe';
}
