<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Points to a transaction without its contents: the company, day book and number, e.g. the
 * original and the reversed transaction of a reversal. Read the transaction itself with the
 * resource of its kind, e.g. `$twinfield->journalTransactions()->get($reference->dayBook, $reference->number)`.
 */
final readonly class TransactionReference
{
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Day book (transaction type) code, e.g. `MEMO`. */
        public string $dayBook,
        /** Transaction number, e.g. `202600003`. */
        public int $number,
    ) {
        if ($dayBook === '') {
            throw new InvalidArgumentException('Day book code must not be empty.');
        }

        if ($number < 1) {
            throw new InvalidArgumentException('Transaction number must be 1 or more.');
        }
    }

    /**
     * From an element with `<office>`, `<code>` and `<number>`, e.g. `<original>` of a reversal.
     */
    public static function fromXml(XmlElement $transaction, CompanyReference $client): self
    {
        $office = $transaction->string('office');

        return new self(
            company: $office === $client->code ? $client : new CompanyReference($client->organisation, $office),
            dayBook: $transaction->string('code'),
            number: $transaction->int('number'),
        );
    }

    /**
     * True for the same transaction: the same company, day book and number.
     */
    public function equals(self $other): bool
    {
        return $this->company->equals($other->company)
            && $this->dayBook === $other->dayBook
            && $this->number === $other->number;
    }
}
