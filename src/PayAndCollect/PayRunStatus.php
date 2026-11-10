<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

/**
 * Status of the current phase of a pay run (`PhaseStatus`).
 */
enum PayRunStatus: string
{
    /** The run is being processed, e.g. after createAsync() or postAsync(). */
    case Started = 'Started';

    case Done = 'Done';

    case DoneWithErrors = 'DoneWithErrors';
}
