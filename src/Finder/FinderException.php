<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

use InvalidArgumentException;
use RuntimeException;

/**
 * Twinfield refused a Finder search with one or more error messages, e.g. an option the
 * Finder type does not support:
 *
 *   try {
 *       $twinfield->finder(FinderType::SubItems)->get();
 *   } catch (FinderException $exception) {
 *       if ($exception->messages[0]->code === FinderErrorCode::OptionMandatory) { … }
 *   }
 */
final class FinderException extends RuntimeException
{
    /**
     * @param list<FinderMessage> $messages The error messages; at least one.
     */
    public function __construct(
        public readonly FinderType $type,
        public readonly array $messages,
    ) {
        if ($messages === []) {
            throw new InvalidArgumentException('A Finder exception needs at least one message.');
        }

        $message = sprintf('Finder %s: %s (%s)', $type->value, $messages[0]->text, $messages[0]->code->value);

        if (count($messages) > 1) {
            $message .= sprintf(' (and %d more errors)', count($messages) - 1);
        }

        parent::__construct($message);
    }
}
