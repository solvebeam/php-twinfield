<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlMessage;

/**
 * A statement of createMany() that Twinfield imported.
 */
final readonly class CreatedStatement
{
    /**
     * @param list<ProcessXmlMessage> $warnings
     */
    public function __construct(
        /** Position in the inputs of createMany(), from 0. */
        public int $index,
        public ElectronicBankStatementInput $input,
        public ElectronicBankStatement $statement,
        public array $warnings,
    ) {
    }
}
