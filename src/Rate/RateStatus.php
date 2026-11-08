<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rate;

/**
 * Status of a rate (`projectrate/@status`) or of one of its price lines (`ratechange/@status`).
 */
enum RateStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';

    /** Twinfield hides a rate instead of deleting it when it is in use. */
    case Hidden = 'hide';
}
