<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\DeletedTransaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\CommandQuery\Query;
use SolveBeam\Twinfield\DeletedTransaction\DeletedTransaction;
use SolveBeam\Twinfield\DeletedTransaction\DeletedTransactionsServiceFaultCode;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live DeletedTransactionsService queries (read-only) against TWINFIELD_COMPANY.
 */
final class DeletedTransactionResourceTest extends IntegrationTestCase
{
    public function testListsDeletedTransactionsNewestFirst(): void
    {
        $transactions = $this->twinfield->deletedTransactions()->list();

        if ($transactions === []) {
            self::markTestSkipped('The company has no deleted transactions.');
        }

        $dates = array_map(static fn (DeletedTransaction $transaction): DateTimeImmutable => $transaction->deletionDate, $transactions);
        $sorted = $dates;
        usort($sorted, static fn (DateTimeImmutable $a, DateTimeImmutable $b): int => $b <=> $a);

        self::assertEquals($sorted, $dates, 'Newest deletion first.');
    }

    public function testFiltersOnDayBookAndDeletionDay(): void
    {
        $all = $this->twinfield->deletedTransactions()->list();

        if ($all === []) {
            self::markTestSkipped('The company has no deleted transactions.');
        }

        $first = $all[0];
        $day = $first->deletionDate->setTime(0, 0);

        // A time later on the day must not matter: Twinfield only looks at the date.
        $transactions = $this->twinfield->deletedTransactions()->list(
            dayBook: strtolower($first->dayBook),
            from: $day->setTime(23, 59),
            to: $day,
        );

        self::assertContainsEquals($first, $transactions);

        foreach ($transactions as $transaction) {
            self::assertSame($first->dayBook, $transaction->dayBook);
            self::assertSame($day->format('Y-m-d'), $transaction->deletionDate->format('Y-m-d'));
        }
    }

    public function testGivesNothingForAFutureDay(): void
    {
        self::assertSame([], $this->twinfield->deletedTransactions()->list(from: new DateTimeImmutable('+1 year')));
    }

    public function testThrowsInvalidDaybook(): void
    {
        $this->assertFault(DeletedTransactionsServiceFaultCode::InvalidDaybook, ['Daybook' => 'NOPE9']);
    }

    public function testThrowsInvalidDates(): void
    {
        // list() checks this itself, so send the query directly. Twinfield accepts a from date one
        // day after the to date (and gives nothing), so take two days.
        $this->assertFault(DeletedTransactionsServiceFaultCode::InvalidDates, [
            'DateFrom' => new DateTimeImmutable('2025-01-03'),
            'DateTo' => new DateTimeImmutable('2025-01-01'),
        ]);
    }

    public function testThrowsInvalidCompanyCode(): void
    {
        $this->assertFault(DeletedTransactionsServiceFaultCode::InvalidCompanyCode, ['CompanyCode' => 'NOPE9']);
    }

    /**
     * @param array<string, string|DateTimeImmutable> $fields
     */
    private function assertFault(DeletedTransactionsServiceFaultCode $code, array $fields): void
    {
        $fields = [
            'CompanyCode' => $fields['CompanyCode'] ?? $this->twinfield->companyCode(),
            'DateFrom' => $fields['DateFrom'] ?? null,
            'DateTo' => $fields['DateTo'] ?? null,
            'Daybook' => $fields['Daybook'] ?? null,
        ];

        try {
            $this->twinfield->query(
                'DeletedTransactionsService',
                new Query('GetDeletedTransactions', $fields),
                'GetDeletedTransactionsResult',
            );
            self::fail('Expected a CommandQueryFaultException.');
        } catch (CommandQueryFaultException $exception) {
            self::assertSame('DeletedTransactionsServiceFault', $exception->type);
            self::assertSame($code->value, $exception->errorCode);
        }
    }
}
