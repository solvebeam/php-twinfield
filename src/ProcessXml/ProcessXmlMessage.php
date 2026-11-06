<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ProcessXml;

use DOMElement;

/**
 * Error or warning on an element of a ProcessXml response (`msg` and `msgtype` attributes).
 */
final readonly class ProcessXmlMessage
{
    public function __construct(
        public MessageType $type,
        public string $text,
        /** Where the message is, e.g. `transactions/transaction[2]/header/period`. */
        public string $path,
        public DOMElement $element,
    ) {
    }
}
