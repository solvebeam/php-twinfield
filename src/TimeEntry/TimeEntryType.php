<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

/**
 * Kind of time entry (`<code>` in the header).
 */
enum TimeEntryType: string
{
    /** Time on a project, e.g. for a customer. */
    case Direct = 'DIRECT';

    /** Time that is not for a project of a customer, e.g. a meeting or training. */
    case Indirect = 'INDIRECT';

    /** Personal time, e.g. leave or illness. */
    case Personal = 'PERSONAL';
}
