<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

/**
 * Twinfield Finder search types.
 *
 * Only types verified against the live API are listed; add more as they are confirmed.
 */
enum FinderType: string
{
    case Articles = 'ART';
    case AssetMethods = 'ASM';
    case Budgets = 'BDS';
    case CashbooksAndBanks = 'BNK';
    case Countries = 'CTR';
    case Currencies = 'CUR';
    case DimensionGroups = 'GRP';
    case DimensionTypes = 'DMT';
    case Dimensions = 'DIM';
    case Offices = 'OFF';

    /** Rates of Time & Expenses (time project rates). */
    case Rates = 'TRT';

    /** Transaction types (daybooks). */
    case TransactionTypes = 'TRS';

    case Users = 'USR';
    case VatCodes = 'VAT';
}
