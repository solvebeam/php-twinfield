<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

/**
 * What an account of a VAT percentage is for (`account/linetype`).
 */
enum VatAccountLineType: string
{
    /** The VAT amount is posted on this account, e.g. VAT payable. */
    case Vat = 'vat';

    /** Part of the calculated VAT is posted on another general ledger account instead. */
    case Detail = 'detail';
}
