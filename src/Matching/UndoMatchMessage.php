<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

/**
 * A message in the result of UndoMatch.
 */
final readonly class UndoMatchMessage
{
    /**
     * @param list<string|null> $parameters Not in use according to the documentation; null for
     *                                      `<string xsi:nil="true"/>`.
     */
    public function __construct(
        public UndoMatchMessageType $type,
        /** Use this to act on a message. */
        public UndoMatchMessageCode $code,
        /** Readable text, in the language of the user. Only for logging or showing to the user. */
        public string $text,
        public array $parameters,
    ) {
    }
}
