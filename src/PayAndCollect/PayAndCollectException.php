<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use InvalidArgumentException;
use RuntimeException;

/**
 * Twinfield refused a Pay & Collect operation with one or more error messages, e.g. because the run
 * is not in the right phase, or was changed by someone else since it was read.
 *
 *   try {
 *       $payAndCollect->authorise($run);
 *   } catch (PayAndCollectException $exception) {
 *       echo $exception->operation, ': ', $exception->messages[0]->text, PHP_EOL;
 *   }
 */
final class PayAndCollectException extends RuntimeException
{
    /**
     * @param string $operation The SOAP operation, e.g. `Authorize`.
     * @param list<PayAndCollectMessage> $messages The error messages; at least one.
     */
    public function __construct(
        public readonly string $operation,
        public readonly array $messages,
    ) {
        if ($messages === []) {
            throw new InvalidArgumentException('A Pay & Collect exception needs at least one message.');
        }

        $message = sprintf('%s: %s', $operation, $messages[0]->text);

        if (count($messages) > 1) {
            $message .= sprintf(' (and %d more errors)', count($messages) - 1);
        }

        parent::__construct($message);
    }
}
