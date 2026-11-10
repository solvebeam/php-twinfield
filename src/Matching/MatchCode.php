<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;

/**
 * The kind of lines a match set matches (`matchcode`).
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/matching/
 */
enum MatchCode: string
{
    /** Lines on suspense accounts (tussenrekeningen), a balance sheet account. */
    case SuspenseAccounts = '070';

    /** Lines of a customer, e.g. a sales invoice and its payment. */
    case Customers = '170';

    /** Lines of a supplier, e.g. a purchase invoice and its payment. */
    case Suppliers = '270';

    /** Lines of a project. */
    case Projects = '370';

    /**
     * The dimension type of the matched lines, e.g. to undo the match with MatchingResource::undo().
     */
    public function dimensionType(): DimensionTypeCode
    {
        return match ($this) {
            self::SuspenseAccounts => DimensionTypeCode::BalanceSheet,
            self::Customers => DimensionTypeCode::AccountsReceivable,
            self::Suppliers => DimensionTypeCode::AccountsPayable,
            self::Projects => DimensionTypeCode::Projects,
        };
    }
}
