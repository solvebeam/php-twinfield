<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\GeneralLedgerAccount;

/**
 * What a child validation checks (`childvalidation/@type`).
 */
enum ChildValidationType: string
{
    /** One dimension, by code, e.g. cost center `00100`. */
    case Code = 'code';

    /** Any dimension of a dimension type, e.g. `KPL`. */
    case Type = 'type';

    /** Any dimension in a dimension group, e.g. `2REL`. */
    case Group = 'group';
}
