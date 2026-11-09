<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

/**
 * Status of a sales invoice.
 */
enum SalesInvoiceStatus: string
{
    /** Only returned when reading; you can't save an invoice with this status. */
    case Default = 'default';

    /** Provisional: the invoice can still be changed. */
    case Concept = 'concept';

    /** Final: no changes anymore, and Twinfield has booked the sales transaction. */
    case Final = 'final';
}
