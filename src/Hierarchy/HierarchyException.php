<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use InvalidArgumentException;
use RuntimeException;

/**
 * Twinfield refused a hierarchies request with one or more error messages.
 *
 *   try {
 *       $hierarchies->save($input);
 *   } catch (HierarchyException $exception) {
 *       foreach ($exception->messages as $message) {
 *           echo $message->node, ' ', $message->code->name, ' ', $message->text, PHP_EOL;
 *       }
 *   }
 *
 * An unknown reporting structure code is HierarchyNotFoundException.
 */
final class HierarchyException extends RuntimeException
{
    /**
     * @param list<HierarchyMessage> $messages The error messages; at least one.
     */
    public function __construct(
        public readonly string $operation,
        public readonly array $messages,
    ) {
        if ($messages === []) {
            throw new InvalidArgumentException('A hierarchy exception needs at least one message.');
        }

        $first = $messages[0];
        $message = sprintf(
            '%s: %s%s (%s)',
            $operation,
            $first->node === null ? '' : 'node ' . $first->node . ': ',
            $first->text,
            $first->code->value,
        );

        if (count($messages) > 1) {
            $message .= sprintf(' (and %d more errors)', count($messages) - 1);
        }

        parent::__construct($message);
    }
}
