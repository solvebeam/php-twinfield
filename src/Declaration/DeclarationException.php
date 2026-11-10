<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration;

use InvalidArgumentException;
use RuntimeException;

/**
 * Twinfield refused a declarations request with one or more error messages.
 *
 *   try {
 *       $twinfield->declarations()->vatReturnPaymentReference($id);
 *   } catch (DeclarationException $exception) {
 *       if ($exception->messages[0]->code === DeclarationLoadMessage::InvalidDocumentType) { … }
 *   }
 *
 * An unknown document ID is not a message but a SOAP fault (ClassicServiceFaultException).
 */
final class DeclarationException extends RuntimeException
{
    /**
     * @param list<DeclarationMessage> $messages The error messages; at least one.
     */
    public function __construct(
        public readonly string $operation,
        public readonly array $messages,
    ) {
        if ($messages === []) {
            throw new InvalidArgumentException('A declaration exception needs at least one message.');
        }

        $message = sprintf('%s: %s (%s)', $operation, $messages[0]->text, $messages[0]->code->value);

        if (count($messages) > 1) {
            $message .= sprintf(' (and %d more errors)', count($messages) - 1);
        }

        parent::__construct($message);
    }
}
