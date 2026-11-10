<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankBook;

use SolveBeam\Twinfield\CommandQuery\ResultElement;

/**
 * Automatic bank processing of a bank book, as Twinfield returns it. Read-only: the
 * BankBookService has no command to change it.
 */
final readonly class AutomaticBankProcessing
{
    public function __construct(
        public AutomaticBankProcessingSignUpState $signUpState,
        /** Bank statements are imported automatically. */
        public bool $processStatements,
        /** Payments are sent to the bank automatically. */
        public bool $processPayments,
        /** Direct debits are sent to the bank automatically. */
        public bool $processDirectDebits,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResult(ResultElement $element): self
    {
        return new self(
            signUpState: $element->enum('SignUpState', AutomaticBankProcessingSignUpState::class),
            processStatements: $element->bool('ProcessStatements'),
            processPayments: $element->bool('ProcessPayments'),
            processDirectDebits: $element->bool('ProcessDirectDebits'),
        );
    }
}
