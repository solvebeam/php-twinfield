<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

/**
 * How the invoices of a new pay run are summarised (`Method`). Without one, Twinfield uses the
 * setting of the pay type.
 */
enum PayRunMethod: string
{
    /** The invoices are not summarised. */
    case Detailed = 'Detailed';

    /** Summarised per relation and currency; credit invoices are not selected. */
    case Summarise = 'Summarise';

    /** Summarised per relation and currency, with credit invoices deducted. */
    case SummariseAndDeduct = 'SummariseAndDeduct';
}
