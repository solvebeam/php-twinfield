<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * An electronic bank statement as Twinfield keeps it (BankStatementService), with its lines and
 * how Twinfield allocated them.
 *
 * Not the same as ElectronicBankStatement, the result of an import: that one has only the
 * dimensions per line. This one also has the allocation rule, the matchings and whether the
 * statement is posted.
 *
 * Dates have no time zone in the response; they are in the time zone of PHP, like the other
 * dates this library reads.
 */
final readonly class BankStatement
{
    /**
     * @param list<BankStatementLine> $lines
     */
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Code of the bank book, e.g. `RABO`. */
        public string $bankBook,
        /** Statement number, e.g. `1674`. */
        public int $number,
        /** Sub ID, to tell apart statements imported twice with the same number; usually `1`. */
        public int $subId,
        /** Account number (BBAN) of the bank account; null when Twinfield gives none. */
        public ?string $accountNumber,
        /** IBAN of the bank account; null when Twinfield gives none. */
        public ?string $iban,
        public DateTimeImmutable $date,
        /** Currency code, e.g. `EUR`. */
        public string $currency,
        public Amount $openingBalance,
        public Amount $closingBalance,
        public array $lines,
        /** Number of the bank transaction the statement is posted as; null while not posted. */
        public ?int $transactionNumber,
    ) {
        if ($bankBook === '' || $currency === '') {
            throw new InvalidArgumentException('Bank book and currency of a bank statement must not be empty.');
        }
    }

    /**
     * From a `BankStatement` element of the `GetBankStatementsResult`.
     *
     * @internal
     */
    public static function fromResult(ResultElement $statement, CompanyReference $company): self
    {
        return new self(
            company: $company,
            bankBook: $statement->string('Code'),
            number: $statement->int('Number'),
            subId: $statement->int('SubId'),
            accountNumber: $statement->nullableString('AccountNumber'),
            iban: $statement->nullableString('Iban'),
            date: $statement->dateTime('StatementDate'),
            currency: $statement->string('Currency'),
            openingBalance: $statement->amount('OpeningBalance'),
            closingBalance: $statement->amount('ClosingBalance'),
            lines: array_map(BankStatementLine::fromResult(...), $statement->list('Lines', 'BankStatementLine')),
            transactionNumber: $statement->nullableWholeNumber('TransactionNumber'),
        );
    }

    public function isPosted(): bool
    {
        return $this->transactionNumber !== null;
    }

    /**
     * Sum of the line amounts: the closing balance minus the opening balance.
     */
    public function total(): Amount
    {
        return Amount::sum(array_map(static fn (BankStatementLine $line): Amount => $line->amount, $this->lines));
    }
}
