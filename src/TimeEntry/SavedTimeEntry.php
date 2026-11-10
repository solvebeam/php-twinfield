<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlMessage;

/**
 * A time entry of saveMany() that Twinfield saved.
 */
final readonly class SavedTimeEntry
{
    /**
     * @param list<ProcessXmlMessage> $warnings
     */
    public function __construct(
        /** Position in the inputs of saveMany(), from 0. */
        public int $index,
        public TimeEntryInput $input,
        public TimeEntry $timeEntry,
        public array $warnings,
    ) {
    }
}
