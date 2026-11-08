<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

/**
 * How the payment discount is calculated (`general/paymentdiscount`).
 */
enum PaymentDiscount: string
{
    /** Over the amount excluding VAT. */
    case VatExclusive = 'vatexclusive';

    /** Over the amount including VAT; not seen in live responses yet. */
    case VatInclusive = 'vatinclusive';
}
