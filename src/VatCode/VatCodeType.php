<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

/**
 * Type of a VAT code: for sales or for purchases (`vat/type`, `vatcode/@type`).
 */
enum VatCodeType: string
{
    /** VAT you pay on purchases and can deduct (input tax, voorbelasting). */
    case Purchase = 'purchase';

    /** VAT you charge on sales (output tax). */
    case Sales = 'sales';
}
