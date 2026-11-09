<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * Points to one line of a transaction: the company, day book, number and line number, e.g. the
 * `<key>` of a row in browse data. Together they identify a transaction line, e.g. in a shadow
 * database.
 *
 * Browse data also lists draft transactions, which have no number yet: Twinfield gives them
 * number `-1`, so several drafts in a day book share the same key. See isDraft().
 */
final readonly class TransactionLineReference
{
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Day book (transaction type) code, e.g. `VRK`. */
        public string $dayBook,
        /** Transaction number, e.g. `202600003`, or `-1` for a draft transaction. */
        public int $number,
        /** Line number within the transaction, from 1. */
        public int $line,
    ) {
        if ($dayBook === '') {
            throw new InvalidArgumentException('Day book code must not be empty.');
        }

        if ($number < 1 && $number !== -1) {
            throw new InvalidArgumentException(sprintf(
                'Transaction number must be 1 or more, or -1 for a draft, got %d.',
                $number,
            ));
        }

        if ($line < 1) {
            throw new InvalidArgumentException(sprintf('Line number must be 1 or more, got %d.', $line));
        }
    }

    /**
     * A draft transaction has no number yet (`-1`), so the reference is not unique.
     */
    public function isDraft(): bool
    {
        return $this->number === -1;
    }

    /**
     * The transaction of the line, e.g. to read it with `journalTransactions()->get()`.
     *
     * @throws LogicException For a draft transaction, which has no number yet.
     */
    public function transaction(): TransactionReference
    {
        if ($this->isDraft()) {
            throw new LogicException(sprintf(
                'Line %d of a draft transaction in day book %s has no transaction number yet.',
                $this->line,
                $this->dayBook,
            ));
        }

        return new TransactionReference($this->company, $this->dayBook, $this->number);
    }

    /**
     * True for the same line: the same company, day book, number and line number.
     */
    public function equals(self $other): bool
    {
        return $this->company->equals($other->company)
            && $this->dayBook === $other->dayBook
            && $this->number === $other->number
            && $this->line === $other->line;
    }
}
