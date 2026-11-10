<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlMessage;

/**
 * A time entry of saveMany() that Twinfield refused.
 */
final readonly class FailedTimeEntry
{
    /**
     * @param list<ProcessXmlMessage> $errors
     * @param list<ProcessXmlMessage> $warnings
     */
    public function __construct(
        /** Position in the inputs of saveMany(), from 0. */
        public int $index,
        public TimeEntryInput $input,
        /** `path` says where, e.g. `teqs/teq[2]/header/user`. */
        public array $errors,
        public array $warnings,
    ) {
    }

    /**
     * All errors on one line, e.g. for a log.
     */
    public function message(): string
    {
        return implode('; ', array_map(
            static fn (ProcessXmlMessage $error): string => sprintf('%s: %s', $error->path, $error->text),
            $this->errors,
        ));
    }
}
