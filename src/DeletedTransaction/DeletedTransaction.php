<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DeletedTransaction;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A deleted transaction, as returned by the DeletedTransactionsService.
 *
 * Dates have no time zone in the response; they are in the time zone of PHP, like the
 * other dates this library reads.
 */
final readonly class DeletedTransaction
{
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Day book (transaction type) code, e.g. `BNK`, `INK` or `MEMO`. */
        public string $dayBook,
        /** Transaction number, e.g. `202600045`. */
        public int $number,
        /** Date of the transaction (without time). */
        public DateTimeImmutable $transactionDate,
        /** When the transaction was deleted, e.g. `2026-11-04 05:31:52.420`. */
        public DateTimeImmutable $deletionDate,
        /** Code of the user who deleted the transaction, e.g. `JDOE`. */
        public string $user,
        /** Reason given when deleting; Twinfield requires one. */
        public string $reason,
    ) {
        foreach (['day book' => $dayBook, 'user' => $user, 'reason' => $reason] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Deleted transaction %s must not be empty.', $field));
            }
        }

        if ($number < 1) {
            throw new InvalidArgumentException('Transaction number must be 1 or more.');
        }
    }

    /**
     * From a `DeletedTransaction` element of the `GetDeletedTransactions` result.
     */
    public static function fromResult(ResultElement $transaction, CompanyReference $company): self
    {
        return new self(
            company: $company,
            dayBook: $transaction->string('Daybook'),
            number: $transaction->wholeNumber('TransactionNumber'),
            transactionDate: $transaction->dateTime('TransactionDate'),
            deletionDate: $transaction->dateTime('DeletionDate'),
            user: $transaction->string('User'),
            reason: $transaction->string('ReasonForDeletion'),
        );
    }
}
