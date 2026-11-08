<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

/**
 * How a supplier is paid to this bank account (`bank/wayofpayment`). Only for the payment
 * type BTL91 (foreign payments).
 */
enum WayOfPayment: string
{
    case Normal = '0';
    case Cheque = '1';
    case Basis = '2';
}
