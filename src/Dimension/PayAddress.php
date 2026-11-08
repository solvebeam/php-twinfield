<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

/**
 * Where a cheque is sent (`bank/payaddress`). Only with WayOfPayment::Cheque.
 */
enum PayAddress: string
{
    case BankPayer = '1';
    case Payer = '2';
    case Beneficiary = '3';
}
