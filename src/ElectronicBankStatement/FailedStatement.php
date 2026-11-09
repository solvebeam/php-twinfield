<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlMessage;

/**
 * A statement of createMany() that Twinfield refused.
 */
final readonly class FailedStatement
{
    /**
     * @param list<ProcessXmlMessage> $errors
     * @param list<ProcessXmlMessage> $warnings
     */
    public function __construct(
        /** Position in the inputs of createMany(), from 0. */
        public int $index,
        public ElectronicBankStatementInput $input,
        /** At least one; `path` says where, e.g. `statements/statement[2]/transactions/transaction`. */
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
