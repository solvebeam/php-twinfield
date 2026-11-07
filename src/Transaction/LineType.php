<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

/**
 * Type of a transaction line (`<line type="…">`).
 */
enum LineType: string
{
    /** The balance side: the bank or cash account, or the customer or supplier of an invoice. */
    case Total = 'total';
    /** The other side: what the money is for, without VAT. */
    case Detail = 'detail';
    /** VAT amount per VAT code. */
    case Vat = 'vat';
}
