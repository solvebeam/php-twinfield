<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlMessage;

/**
 * A transaction of saveMany() that Twinfield refused.
 */
final readonly class FailedTransaction
{
    /**
     * @param list<ProcessXmlMessage> $errors
     * @param list<ProcessXmlMessage> $warnings
     */
    public function __construct(
        /** Position in the inputs of saveMany(), from 0. */
        public int $index,
        public TransactionInput $input,
        /** At least one; `path` says where, e.g. `transactions/transaction[2]/lines/line[2]/dim2`. */
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
