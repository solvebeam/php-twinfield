<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

/**
 * Kind of event in the matching audit trail.
 */
enum MatchingEventType: string
{
    case Matched = 'Matched';
    /** The match was undone; the event has no match date. */
    case Unmatched = 'Unmatched';
}
