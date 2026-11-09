<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A transaction line that an allocation of a bank statement line is matched with, e.g. a
 * purchase invoice `INK 202601319` line 1.
 */
final readonly class BankStatementMatching
{
    public function __construct(
        /** Day book (transaction type) code of the matched transaction, e.g. `INK`. */
        public string $dayBook,
        /** Number of the matched transaction, e.g. `202601319`. */
        public int $number,
        /** Line of the matched transaction, e.g. `1`. */
        public int $lineId,
        /** Matched value in the base currency, with the sign of the matched line (negative for a purchase invoice). */
        public Amount $baseValue,
    ) {
        if ($dayBook === '') {
            throw new InvalidArgumentException('Day book of a matching must not be empty.');
        }
    }

    public static function fromResult(ResultElement $matching): self
    {
        return new self(
            dayBook: $matching->string('Code'),
            number: $matching->wholeNumber('Number'),
            lineId: $matching->int('LineId'),
            baseValue: $matching->amount('BaseValue'),
        );
    }
}
