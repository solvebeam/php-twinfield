<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A condition of the allocation rule that allocated a bank statement line, e.g. "the IBAN of
 * the other party" or "the description contains `Smart Pay`".
 *
 * Which fields are filled depends on the type: `text` for Description, Reference and the
 * contra fields, `amountOperator` and `amount` for Amount.
 */
final readonly class BankStatementAllocationMethod
{
    public function __construct(
        public BankStatementAllocationMethodType $type,
        public ?string $text,
        public ?BankStatementAmountOperator $amountOperator,
        public ?Amount $amount,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResult(ResultElement $method): self
    {
        return new self(
            type: $method->enum('Type', BankStatementAllocationMethodType::class),
            text: $method->nullableString('Text'),
            amountOperator: $method->nullableEnum('AmountOperator', BankStatementAmountOperator::class),
            amount: $method->nullableAmount('Amount'),
        );
    }
}
