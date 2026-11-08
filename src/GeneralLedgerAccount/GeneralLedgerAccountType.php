<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\GeneralLedgerAccount;

/**
 * The two dimension types of general ledger accounts. Both have the same settings.
 */
enum GeneralLedgerAccountType: string
{
    /** Balance sheet accounts, e.g. `1300` Debtors. */
    case BalanceSheet = 'BAS';

    /** Profit and loss accounts, e.g. `8000` Revenue. */
    case ProfitAndLoss = 'PNL';

    /**
     * The read-only `financials/accounttype` Twinfield gives accounts of this type.
     */
    public function accountType(): string
    {
        return match ($this) {
            self::BalanceSheet => 'balance',
            self::ProfitAndLoss => 'profitandloss',
        };
    }

    /**
     * Name in messages, e.g. `balance sheet account`.
     */
    public function label(): string
    {
        return match ($this) {
            self::BalanceSheet => 'balance sheet account',
            self::ProfitAndLoss => 'profit and loss account',
        };
    }
}
