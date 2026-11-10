<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Period;

use RuntimeException;
use Throwable;

/**
 * The company has no such period: the year does not exist (PeriodServiceFault `YearNotFound`),
 * or the year has no period with this number.
 */
final class PeriodNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly Period $period,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Period %s not found.', $period), 0, $previous);
    }
}
