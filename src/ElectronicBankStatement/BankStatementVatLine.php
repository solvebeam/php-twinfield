<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * VAT of an allocation of a bank statement line.
 */
final readonly class BankStatementVatLine
{
    public function __construct(
        /** VAT code, e.g. `VH`. */
        public string $code,
        public Amount $turnover,
        public Amount $value,
        /** Whether a user changed the VAT value Twinfield calculated. */
        public bool $valueOverridden,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('VAT code of a VAT line must not be empty.');
        }
    }

    /**
     * @internal
     */
    public static function fromResult(ResultElement $vatLine): self
    {
        return new self(
            code: $vatLine->string('Code'),
            turnover: $vatLine->amount('Turnover'),
            value: $vatLine->amount('Value'),
            valueOverridden: $vatLine->bool('ValueOverridden'),
        );
    }
}
