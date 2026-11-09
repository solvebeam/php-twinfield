<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

/**
 * Browse codes: the predefined definitions of financial data you can request with browse data.
 *
 *   $result = $twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)->columns(…)->get();
 *
 * The codes and descriptions are those of the Twinfield documentation. `browse()` also takes a
 * string, so a code Twinfield adds later works without an SDK update. The fields of each code are
 * in `browse-definitions/` in the repository.
 *
 * @see https://developers.twinfield.com/documentation/api/request-data/browse-data/#determine-which-browse-definition-to-use
 */
enum BrowseCode: string
{
    case GeneralLedgerTransactions = '000';
    case TransactionsToBeMatched = '010';
    case TransactionList = '020';
    case CustomerTransactions = '100';
    case SupplierTransactions = '200';
    case ProjectTransactions = '300';
    case AssetTransactions = '301';
    case CashTransactions = '400';
    case BankTransactions = '410';
    case CostCenters = '900';
    case GeneralLedgerDetails = '030_1';
    case GeneralLedgerDetailsV2 = '030_2';
    case GeneralLedgerDetailsV3 = '030_3';
    case GeneralLedgerIntercompany = '031';
    case GeneralLedgerIntercompanyV2 = '031_2';
    case AnnualReportTotals = '040_1';
    case AnnualReportYearToDate = '050_1';
    case AnnualReportTotalsMulticurrency = '060';
    case Customers = '130_1';
    case CustomersV2 = '130_2';
    case CustomersV3 = '130_3';
    case CreditManagement = '164';
    case Suppliers = '230_1';
    case SuppliersV2 = '230_2';
    case FixedAssets = '302_1';
    case TimeAndExpensesTotals = '610_1';
    case TimeAndExpensesMulticurrency = '620';
    case TimeAndExpensesDetails = '650_1';
    case TimeAndExpensesTotalsPerWeek = '651_1';
    case TimeAndExpensesTotalsPerPeriod = '652_1';
    case TimeAndExpensesBillingDetails = '660_1';
    case TimeAndExpensesBillingPerWeek = '661_1';
    case TimeAndExpensesBillingPerPeriod = '662_1';
    case TransactionSummary = '670';
    case BankLinkDetails = '680';
    case BankLinkDetailsV2 = '680_2';
    case VatReturnStatus = '690';
    case HierarchyAccess = '700';

    /**
     * The description in the Twinfield documentation, e.g. `General Ledger (details) (v3)`.
     * Twinfield itself returns the name in the language of the user (`BrowseDefinition::$name`).
     */
    public function label(): string
    {
        return match ($this) {
            self::GeneralLedgerTransactions => 'General ledger transactions',
            self::TransactionsToBeMatched => 'Transactions still to be matched',
            self::TransactionList => 'Transaction list',
            self::CustomerTransactions => 'Customer transactions',
            self::SupplierTransactions => 'Supplier transactions',
            self::ProjectTransactions => 'Project transactions',
            self::AssetTransactions => 'Asset transactions',
            self::CashTransactions => 'Cash transactions',
            self::BankTransactions => 'Bank transactions',
            self::CostCenters => 'Cost centers',
            self::GeneralLedgerDetails => 'General Ledger (details)',
            self::GeneralLedgerDetailsV2 => 'General Ledger (details) (v2)',
            self::GeneralLedgerDetailsV3 => 'General Ledger (details) (v3)',
            self::GeneralLedgerIntercompany => 'General Ledger (intercompany)',
            self::GeneralLedgerIntercompanyV2 => 'General Ledger (intercompany)(v2)',
            self::AnnualReportTotals => 'Annual Report (totals)',
            self::AnnualReportYearToDate => 'Annual Report (YTD)',
            self::AnnualReportTotalsMulticurrency => 'Annual Report (totals multicurrency)',
            self::Customers => 'Customers',
            self::CustomersV2 => 'Customers (v2)',
            self::CustomersV3 => 'Customers (v3)',
            self::CreditManagement => 'Credit Management',
            self::Suppliers => 'Suppliers',
            self::SuppliersV2 => 'Suppliers (v2)',
            self::FixedAssets => 'Fixed Assets',
            self::TimeAndExpensesTotals => 'Time & Expenses (Totals)',
            self::TimeAndExpensesMulticurrency => 'Time & Expenses (Multicurrency)',
            self::TimeAndExpensesDetails => 'Time & Expenses (Details)',
            self::TimeAndExpensesTotalsPerWeek => 'Time & Expenses (Totals per week)',
            self::TimeAndExpensesTotalsPerPeriod => 'Time & Expenses (Totals per period)',
            self::TimeAndExpensesBillingDetails => 'Time & Expenses (Billing details)',
            self::TimeAndExpensesBillingPerWeek => 'Time & Expenses (Billing per week)',
            self::TimeAndExpensesBillingPerPeriod => 'Time & Expenses (Billing per period)',
            self::TransactionSummary => 'Transaction summary',
            self::BankLinkDetails => 'Bank link details',
            self::BankLinkDetailsV2 => 'Bank link details (v2)',
            self::VatReturnStatus => 'Vat Return status',
            self::HierarchyAccess => 'Hierarchy access',
        };
    }
}
