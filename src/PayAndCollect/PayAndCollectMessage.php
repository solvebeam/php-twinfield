<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

/**
 * A message in the result of a Pay & Collect operation, e.g. why a transaction was not selected.
 *
 * Every operation has its own message type in the WSDL (`MessageOfCreateRunResult`,
 * `MessageOfModifyResult`, …), but they are the same apart from the code, which is always
 * `UnknownTextualMessage`. So there is one class, and only the text tells what happened.
 */
final readonly class PayAndCollectMessage
{
    /**
     * @param list<string|null> $parameters Not in use according to the documentation; null for
     *                                      `<string xsi:nil="true"/>`.
     */
    public function __construct(
        public PayAndCollectMessageType $type,
        /** Readable text, in the language of the user. */
        public string $text,
        /** Always `UnknownTextualMessage` according to the documentation and the WSDL. */
        public string $code,
        public array $parameters,
    ) {
    }
}
