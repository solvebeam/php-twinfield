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
    case CashbooksAndBanks = 'BNK';
    case Countries = 'CTR';
    case Currencies = 'CUR';
    case DimensionGroups = 'GRP';
    case DimensionTypes = 'DMT';
    case Dimensions = 'DIM';
    case Offices = 'OFF';
    case TransactionTypes = 'TRT';
    case Users = 'USR';
    case VatCodes = 'VAT';
}
