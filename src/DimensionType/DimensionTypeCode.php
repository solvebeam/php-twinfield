<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionType;

/**
 * The standard Twinfield dimension types. Resources accept these or any code as a string.
 *
 *   $customers = $twinfield->dimensionTypes()->get(DimensionTypeCode::AccountsReceivable);
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/dimension-types/#read
 */
enum DimensionTypeCode: string
{
    /** Balance sheet accounts (general ledger), financial level 1. */
    case BalanceSheet = 'BAS';

    /** Profit and loss accounts (general ledger), financial level 1. */
    case ProfitAndLoss = 'PNL';

    /** Accounts payable: suppliers, financial level 2. */
    case AccountsPayable = 'CRD';

    /** Accounts receivable: customers, financial level 2. */
    case AccountsReceivable = 'DEB';

    /** Cost centers, financial level 2. */
    case CostCenters = 'KPL';

    /** Fixed assets, financial level 3. */
    case Assets = 'AST';

    /** Projects, financial level 3. */
    case Projects = 'PRJ';

    /** Activities, financial level 4. */
    case Activities = 'ACT';
}
