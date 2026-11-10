<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\TransactionXml;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * An electronic bank statement as Twinfield returns it after importing.
 *
 * Twinfield fills in what the input left out (bank book, date, currency, number, balances) and
 * allocates the lines; see the dimensions of each line.
 */
final readonly class ElectronicBankStatement
{
    /**
     * @param list<ElectronicBankStatementLine> $lines
     */
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Code of the bank book, e.g. `BNK`. */
        public string $bankBook,
        /** Name of the bank book; null when Twinfield gives none. */
        public ?string $bankBookName,
        /**
         * Account number (BBAN) and IBAN of the bank account. Twinfield returns what the input had
         * (see the response example of the documentation, with only the IBAN), so either may be null.
         */
        public ?string $account,
        public ?string $iban,
        public DateTimeImmutable $date,
        /** Currency code, e.g. `EUR`. */
        public string $currency,
        /** Statement number, e.g. `2`. */
        public int $number,
        public Amount $startValue,
        public Amount $closeValue,
        public array $lines,
    ) {
        if ($bankBook === '' || $currency === '') {
            throw new InvalidArgumentException('Bank book and currency of a bank statement must not be empty.');
        }
    }

    /**
     * From a `<statement>` of the response.
     *
     * @internal
     */
    public static function fromXml(XmlElement $statement, CompanyReference $client): self
    {
        $office = $statement->string('office');
        $code = $statement->child('code');
        $bankBookName = $code->hasAttribute('name') ? $code->attribute('name') : '';
        $transactions = $statement->child('transactions');
        $lines = array_map(ElectronicBankStatementLine::fromXml(...), $transactions->list('transaction'));

        if ($transactions->hasAttribute('count') && $transactions->intAttribute('count') !== count($lines)) {
            throw new UnexpectedResponseException(sprintf(
                '%s/@count is %d, but it has %d lines.',
                $transactions->path,
                $transactions->intAttribute('count'),
                count($lines),
            ));
        }

        return new self(
            company: $office === $client->code ? $client : new CompanyReference($client->organisation, $office),
            bankBook: $statement->string('code'),
            bankBookName: $bankBookName === '' ? null : $bankBookName,
            account: TransactionXml::optionalChild($statement, 'account')?->text(),
            iban: TransactionXml::optionalChild($statement, 'iban')?->text(),
            date: TransactionXml::date($statement, 'date'),
            currency: $statement->string('currency'),
            number: $statement->int('statementnumber'),
            startValue: TransactionXml::amount($statement, 'startvalue'),
            closeValue: TransactionXml::amount($statement, 'closevalue'),
            lines: $lines,
        );
    }

    /**
     * Sum of the lines, positive when more money came in than went out.
     */
    public function total(): Amount
    {
        $total = Amount::zero();

        foreach ($this->lines as $line) {
            $total = $total->plus($line->signedValue());
        }

        return $total;
    }
}
