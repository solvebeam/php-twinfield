<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\DeletedTransaction;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\DeletedTransaction\DeletedTransaction;
use SolveBeam\Twinfield\DeletedTransaction\DeletedTransactionsServiceFaultCode;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class DeletedTransactionResourceTest extends TestCase
{
    public function testListsDeletedTransactions(): void
    {
        $http = ReplayClient::fixture('deleted-transactions');

        $transactions = $this->twinfield($http)->deletedTransactions()->list();

        self::assertCount(19, $transactions);
        self::assertEquals(
            new DeletedTransaction(
                company: TestTokens::company(),
                dayBook: 'HGSF',
                number: 965764205,
                transactionDate: new DateTimeImmutable('2024-07-27T00:00:00'),
                deletionDate: new DateTimeImmutable('2024-08-02T11:55:10.883'),
                user: 'AVTZ',
                reason: 'Riqk',
            ),
            $transactions[0],
        );
        self::assertEquals(
            new DeletedTransaction(
                company: TestTokens::company(),
                dayBook: 'GWPO',
                number: 752708213,
                transactionDate: new DateTimeImmutable('2023-02-16T00:00:00'),
                deletionDate: new DateTimeImmutable('2023-02-16T15:18:02.533'),
                user: 'HXPEQ',
                reason: 'Tvgi',
            ),
            $transactions[1],
        );
        self::assertSame(192281621, $transactions[18]->number);

        $request = $http->lastRequest();

        self::assertSame('https://example.test/webservices/DeletedTransactionsService.svc', (string) $request->getUri());
        self::assertSame('"http://www.twinfield.com/DeletedTransactionsService/Query"', $request->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            'i:type="a:GetDeletedTransactions"><a:CompanyCode>1000</a:CompanyCode></Query>',
            $this->body($http),
        );
    }

    public function testSendsFiltersInWsdlOrderWithDatesOnly(): void
    {
        $http = ReplayClient::fixture('deleted-transactions-empty');

        $transactions = $this->twinfield($http)->deletedTransactions()->list(
            dayBook: 'BNK',
            from: new DateTimeImmutable('2026-11-01T14:30:00'),
            to: new DateTimeImmutable('2026-11-09T09:15:00'),
        );

        self::assertSame([], $transactions);
        self::assertStringContainsString(
            'i:type="a:GetDeletedTransactions">'
            . '<a:CompanyCode>1000</a:CompanyCode>'
            . '<a:DateFrom>2026-11-01T00:00:00</a:DateFrom>'
            . '<a:DateTo>2026-11-09T00:00:00</a:DateTo>'
            . '<a:Daybook>BNK</a:Daybook>'
            . '</Query>',
            $this->body($http),
        );
    }

    public function testAcceptsTheSameDayForFromAndTo(): void
    {
        $http = ReplayClient::fixture('deleted-transactions-empty');

        // Times differ, the day is the same: everything deleted on that day.
        $this->twinfield($http)->deletedTransactions()->list(
            from: new DateTimeImmutable('2026-11-04T23:00:00'),
            to: new DateTimeImmutable('2026-11-04T08:00:00'),
        );

        self::assertStringContainsString(
            '<a:DateFrom>2026-11-04T00:00:00</a:DateFrom><a:DateTo>2026-11-04T00:00:00</a:DateTo>',
            $this->body($http),
        );
    }

    public function testRejectsFromAfterTo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('From date 2026-11-05 is after to date 2026-11-04.');

        $this->twinfield(new ReplayClient())->deletedTransactions()->list(
            from: new DateTimeImmutable('2026-11-05'),
            to: new DateTimeImmutable('2026-11-04T23:59:59'),
        );
    }

    public function testRejectsAnEmptyDayBook(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield(new ReplayClient())->deletedTransactions()->list(dayBook: '');
    }

    public function testThrowsWhenTheNumberIsNotWhole(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'GetDeletedTransactionsResult/DeletedTransactions/DeletedTransaction[1]/TransactionNumber is not a whole number: "965764205.5".',
        );

        $this->twinfield(ReplayClient::fixture('deleted-transactions-invalid-number'))->deletedTransactions()->list();
    }

    public function testThrowsWhenTheReasonIsMissing(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('GetDeletedTransactionsResult/DeletedTransactions/DeletedTransaction[1]/ReasonForDeletion is missing.');

        $this->twinfield(ReplayClient::fixture('deleted-transactions-missing-reason'))->deletedTransactions()->list();
    }

    public function testThrowsServiceFaultWithCode(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('deleted-transactions-fault-invalid-daybook', 500))
                ->deletedTransactions()
                ->list(dayBook: 'NOPE9');
            self::fail('Expected a CommandQueryFaultException.');
        } catch (CommandQueryFaultException $exception) {
            self::assertSame('DeletedTransactionsServiceFault', $exception->type);
            self::assertSame(
                DeletedTransactionsServiceFaultCode::InvalidDaybook,
                DeletedTransactionsServiceFaultCode::tryFrom($exception->errorCode),
            );
            self::assertSame('Dagboek NOPE9 bestaat niet.', $exception->errorMessage);
        }
    }

    public function testRequiresACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);

        $twinfield->deletedTransactions()->list();
    }

    private function body(ReplayClient $http): string
    {
        return (string) preg_replace('~>\s+<~', '><', (string) $http->lastRequest()->getBody());
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }
}
