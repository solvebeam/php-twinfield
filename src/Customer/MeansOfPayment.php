<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

/**
 * How a customer pays (`financials/meansofpayment`): by direct debit with a payment file, or not.
 */
enum MeansOfPayment: string
{
    case None = 'none';

    /** Direct debit: Twinfield collects the invoices with a payment file. */
    case PaymentFile = 'paymentfile';
}
