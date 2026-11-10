<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\LedgerAccount;

/**
 * Kind of ledger account in the REST API (`type`): a general ledger account or another dimension.
 */
enum LedgerAccountType: string
{
    case Activity = 'activity';
    /** Balance sheet account (`BAS`). */
    case Balance = 'balance';
    case CostCenter = 'costCenter';
    /** Supplier (`CRD`). */
    case Creditor = 'creditor';
    /** Customer (`DEB`). */
    case Debtor = 'debtor';
    case FixedAsset = 'fixedAsset';
    /** Profit and loss account (`PNL`). */
    case ProfitAndLoss = 'profitAndLoss';
    case Project = 'project';
}
