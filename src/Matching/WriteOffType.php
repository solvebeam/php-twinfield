<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

/**
 * What Twinfield does with the match difference of a line (`writeoff/@type`).
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/matching/
 */
enum WriteOffType: string
{
    /** An exchange rate difference. */
    case Currency = 'currency';

    /** A write-off, e.g. a small amount the customer did not pay. */
    case WriteOff = 'writeoff';

    /** A payment discount (deduction). */
    case Discount = 'discount';
}
