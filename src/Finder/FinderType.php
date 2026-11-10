<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

/**
 * Twinfield Finder search types.
 *
 * All types were checked against the live API on 10 November 2026. Unless noted, the columns
 * are code and name. Column names are in the language of the user, so read by position.
 * Left out: `CQT` (cheques), which fails with a server error.
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/finder/
 */
enum FinderType: string
{
    case Articles = 'ART';
    case AssetMethods = 'ASM';

    /** Documented as number and description; live, the column names are code and name. */
    case BillingSchedules = 'BSN';

    case Budgets = 'BDS';
    case CashbooksAndBanks = 'BNK';
    case Certificates = 'CER';
    case Countries = 'CTR';
    case CreditManagementActionCodes = 'CDA';
    case Currencies = 'CUR';

    /** Distribution by periods (budgets, extended trial balance allocation). */
    case DistributionsByPeriods = 'SPM';

    case DimensionGroups = 'GRP';
    case DimensionTypes = 'DMT';
    case Dimensions = 'DIM';
    case FilterMappings = 'FLT';
    case Gateways = 'GWY';

    /** Reporting structures. */
    case Hierarchies = 'HIE';

    /** Nodes of a reporting structure; option `hierarchy`. */
    case HierarchyNodes = 'HND';

    /** ICT country codes. */
    case IctCountries = 'ICTCOUNTRY';

    /** Offices available for InterCompany transactions. */
    case InterCompanyOffices = 'OIC';

    /** Invoice types of the Classic invoicing module; see SalesInvoiceResource::invoiceTypes(). */
    case InvoiceTypes = 'INV';

    case MatchingTypes = 'MAT';

    /** Columns code, name, country, VAT period and first quarter; option `includeid` adds the ID after the name. */
    case Offices = 'OFF';

    case OfficeGroups = 'OFG';

    /** Open (unpaid) invoices: sales and purchase; see SalesTransactionResource::openInvoices(). */
    case OpenInvoices = 'IVT';

    /** Columns paying-in slip number, amount, debit/credit and description. */
    case PayingInSlips = 'PIS';

    /** Payment files (formats), e.g. `BACS`. */
    case PaymentFiles = 'FMT';

    /** Pay and collect types, e.g. `SEPANLCT`. */
    case PaymentTypes = 'PAY';

    /** Columns title (`2025/01`) and name (often empty). */
    case Periods = 'PRD';

    /** Rates of Time & Expenses (time project rates). */
    case Rates = 'TRT';

    case ReminderScenarios = 'RMD';
    case Reports = 'REP';

    /** Sub items of an item; option `article` is mandatory. The default sub item has an empty code. */
    case SubItems = 'SAR';

    /** Tax groups (fiscal unities). */
    case TaxGroups = 'TXG';

    /** Time & quantities transaction types. */
    case TimeAndQuantitiesTransactionTypes = 'TEQ';

    /** Transaction types (daybooks). */
    case TransactionTypes = 'TRS';

    case Translations = 'XLT';
    case UserRoles = 'ROL';
    case Users = 'USR';
    case VatCodes = 'VAT';
    case VatGroups = 'VTB';

    /** Columns country, code and name. */
    case VatGroupCountries = 'VGM';

    /** Columns code, name, country, VAT number, company name and city of relations. */
    case VatNumbers = 'VATN';

    case VatTypes = 'DVT';

    /** Word templates for invoices and reminder letters. */
    case WordTemplates = 'REW';
}
