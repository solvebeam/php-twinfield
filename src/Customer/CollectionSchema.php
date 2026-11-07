<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

/**
 * SEPA direct debit scheme (`financials/collectionschema`).
 */
enum CollectionSchema: string
{
    /** SEPA Core, for consumers and businesses. */
    case Core = 'core';

    /** SEPA Business to Business. */
    case B2b = 'b2b';
}
