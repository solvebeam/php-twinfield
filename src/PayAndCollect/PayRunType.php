<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

/**
 * Type of a pay run: paying suppliers or collecting from customers. Not on the documentation page;
 * the values are from the WSDL.
 */
enum PayRunType: string
{
    /** A payment run: purchase invoices are paid, e.g. pay type `SEPANLCT`. */
    case Payment = 'Payment';

    /** A direct debit run: sales invoices are collected, e.g. pay type `SEPANLDD`. */
    case DirectDebit = 'DirectDebit';
}
