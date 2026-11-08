<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Supplier;

/**
 * How a supplier is paid (`financials/meansofpayment`): not by Twinfield, with a payment file
 * (credit transfer), or by cheque.
 */
enum MeansOfPayment: string
{
    /** Twinfield does not pay the supplier: you pay outside Twinfield. */
    case None = 'none';

    /** Credit transfer: Twinfield pays the invoices with a payment file, e.g. SEPA. */
    case PaymentFile = 'paymentfile';

    /** Twinfield pays the invoices by cheque. */
    case Cheques = 'cheques';
}
