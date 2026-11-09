<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DeletedTransaction;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\CommandQuery\Query;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Twinfield;

/**
 * Deleted transactions of the company, via the DeletedTransactionsService (a Command & Query web service).
 *
 *   $deleted = $twinfield->company('1000')->deletedTransactions();
 *
 *   foreach ($deleted->list(from: new DateTimeImmutable('-7 days')) as $transaction) {
 *       echo $transaction->dayBook, ' ', $transaction->number, ': ', $transaction->reason, PHP_EOL;
 *   }
 *
 * Useful to keep a copy of the transactions in sync: transactions deleted in Twinfield (through
 * the API or in Twinfield itself) show up here, for all day books.
 *
 * Errors are thrown as CommandQueryFaultException; `errorCode` holds a DeletedTransactionsServiceFaultCode value.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/deleted-transactions/
 */
final readonly class DeletedTransactionResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Deleted transactions, newest deletion first, optionally of one day book and deleted between
     * two dates. Without filters you get all deleted transactions of the company, which can be
     * thousands: Twinfield has no paging.
     *
     * Only the date of `$from` and `$to` counts, not the time: Twinfield ignores it. Both days are
     * included, so `from: 2026-11-04, to: 2026-11-04` gives everything deleted on 4 November.
     *
     * @param ?string $dayBook Day book code, e.g. `BNK`; an unknown code gives the fault `InvalidDaybook`.
     * @param ?DateTimeInterface $from First day of deletion.
     * @param ?DateTimeInterface $to Last day of deletion.
     *
     * @return list<DeletedTransaction>
     */
    public function list(
        ?string $dayBook = null,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
    ): array {
        if ($dayBook === '') {
            throw new InvalidArgumentException('Day book code must not be empty; leave it out for all day books.');
        }

        $from = $from === null ? null : self::day($from);
        $to = $to === null ? null : self::day($to);

        if ($from !== null && $to !== null && $from > $to) {
            throw new InvalidArgumentException(sprintf(
                'From date %s is after to date %s.',
                $from->format('Y-m-d'),
                $to->format('Y-m-d'),
            ));
        }

        $company = $this->twinfield->companyCode()
            ?? throw new LogicException('Command & Query web services need a company: use company() first.');

        // Field order follows the WSDL. Twinfield reads the company from this field, not from the header.
        $result = $this->twinfield->query(
            'DeletedTransactionsService',
            new Query('GetDeletedTransactions', [
                'CompanyCode' => $company,
                'DateFrom' => $from,
                'DateTo' => $to,
                'Daybook' => $dayBook,
            ]),
            'GetDeletedTransactionsResult',
        );

        $company = $this->twinfield->companyReference();

        return array_map(
            static fn (ResultElement $transaction): DeletedTransaction
                => DeletedTransaction::fromResult($transaction, $company),
            $result->list('DeletedTransactions', 'DeletedTransaction'),
        );
    }

    /**
     * The date at midnight, so the request shows that only the date counts.
     */
    private static function day(DateTimeInterface $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }
}
