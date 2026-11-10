<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Dimension;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\DimensionChange;
use SolveBeam\Twinfield\Dimension\DimensionChangeStatus;
use SolveBeam\Twinfield\Rest\RestException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class DimensionChangesTest extends TestCase
{
    public function testListsChangedCustomers(): void
    {
        $http = ReplayClient::jsonFixture('customers-changes');

        $page = TestTokens::restClient($http)->customers()
            ->changes(new DateTimeImmutable('2020-01-01T00:00:00+00:00'))
            ->limit(2)
            ->get();

        self::assertEquals(
            new DimensionChange(
                TestTokens::company(),
                '41ba7620-966d-05f1-521d-f4e103ffbe41',
                'Tdwzbjw cir Drqoej M.U.',
                DimensionChangeStatus::Active,
                new DateTimeImmutable('2016-06-20T07:53:06.366381Z'),
            ),
            $page->items[0],
        );
        self::assertSame(
            TestTokens::REST_COMPANY_URL . '/sales/customers/changes?modifiedSince=2020-01-01T00%3A00%3A00%2B00%3A00&limit=2',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testListsChangedSuppliers(): void
    {
        $http = ReplayClient::jsonFixture('suppliers-changes');

        $page = TestTokens::restClient($http)->suppliers()->changes(new DateTimeImmutable('2020-01-01'))->get();

        self::assertCount(2, $page);
        self::assertSame(8, $page->filteredTotal);
        self::assertStringContainsString('/purchase/suppliers/changes?modifiedSince=2020-01-01T00', (string) $http->lastRequest()->getUri());
    }

    public function testReadsDeletedAndArchivedRelations(): void
    {
        $body = (string) json_encode(['items' => [
            ['id' => '0f8fad5b-d9cb-469f-a165-70867728950e', 'name' => 'Old', 'status' => 'deleted', 'modified' => '2026-11-01T10:00:00Z'],
            ['id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7', 'name' => 'Hidden', 'status' => 'archived', 'modified' => '2026-11-02T10:00:00Z'],
        ], 'total' => 2, 'filteredTotal' => 2]);

        $statuses = array_map(
            static fn (DimensionChange $change): DimensionChangeStatus => $change->status,
            TestTokens::restClient(new ReplayClient(ReplayClient::raw(200, $body)))->customers()->changes(new DateTimeImmutable())->get()->items,
        );

        self::assertSame([DimensionChangeStatus::Deleted, DimensionChangeStatus::Archived], $statuses);
    }

    public function testThrowsTheServerErrorOfTwinfield(): void
    {
        // Live, some companies answer every customer changes request with this server error.
        $body = '"An error occurred on the server. Always include the following error reference when you report this error: \'2026-11-10 EZU53\'."';

        $this->expectException(RestException::class);
        $this->expectExceptionMessage('(HTTP 500 Internal Server Error): An error occurred on the server.');

        TestTokens::restClient(new ReplayClient(ReplayClient::raw(500, $body)))->customers()->changes(new DateTimeImmutable())->get();
    }
}
