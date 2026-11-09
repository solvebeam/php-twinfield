<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

/**
 * Payment method of a sales invoice.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Cheque = 'cheque';
    case CashOnDelivery = 'cashondelivery';

    /** Documents against acceptance. */
    case DocumentsAgainstAcceptance = 'da';
}
