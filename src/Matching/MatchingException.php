<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

use InvalidArgumentException;
use RuntimeException;

/**
 * Twinfield refused to undo a match with one or more error messages.
 *
 *   try {
 *       $matching->undo(DimensionTypeCode::AccountsReceivable, '1000', 12);
 *   } catch (MatchingException $exception) {
 *       foreach ($exception->messages as $message) {
 *           echo $message->code->name, ' ', $message->text, PHP_EOL;
 *       }
 *   }
 *
 * An unknown match number is MatchNotFoundException, a subclass.
 */
class MatchingException extends RuntimeException
{
    /**
     * @param list<UndoMatchMessage> $messages The error messages; at least one.
     */
    public function __construct(
        public readonly array $messages,
    ) {
        if ($messages === []) {
            throw new InvalidArgumentException('A matching exception needs at least one message.');
        }

        $message = sprintf('UndoMatch: %s (%s)', $messages[0]->text, $messages[0]->code->value);

        if (count($messages) > 1) {
            $message .= sprintf(' (and %d more errors)', count($messages) - 1);
        }

        parent::__construct($message);
    }
}
