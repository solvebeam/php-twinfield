<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

/**
 * SEPA direct debit scheme of a direct debit run: custom setting `SEPANLDDcollectionschema`.
 */
enum CollectionSchema: string
{
    case Core = '0';

    case B2B = '1';
}
