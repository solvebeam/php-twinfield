<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A transaction line in a MatchInput. Add it with MatchInput::line().
 *
 * The match value and the write-off are optional when matching: without them, Twinfield matches
 * the whole open amount of the line.
 */
final readonly class MatchInputLine
{
    public function __construct(
        /** Day book (transaction type) code, e.g. `VRK`. */
        public string $dayBook,
        /** Transaction number, e.g. `202600070`. */
        public int $number,
        /** Line number within the transaction, e.g. 1 for the total line of an invoice. */
        public int $line,
        /** Only for a partial payment; negative on a credit line. */
        public ?Amount $matchValue = null,
        /** Only for an exchange rate difference, a write-off or a discount. */
        public ?WriteOff $writeOff = null,
    ) {
        XmlValue::code($dayBook);

        if ($number < 1) {
            throw new InvalidArgumentException(sprintf('Transaction number must be 1 or more, got %d.', $number));
        }

        if ($line < 1) {
            throw new InvalidArgumentException(sprintf('Line number must be 1 or more, got %d.', $line));
        }

        if ($matchValue?->isZero() === true) {
            throw new InvalidArgumentException(
                'A match value must not be zero: leave it out to match the open amount.',
            );
        }
    }

    /**
     * True for the same transaction line, whatever the amounts.
     */
    public function isSameLine(self $other): bool
    {
        return $this->dayBook === $other->dayBook
            && $this->number === $other->number
            && $this->line === $other->line;
    }
}
