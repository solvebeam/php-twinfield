<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\LedgerAccount;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\LedgerAccount\LedgerAccount;
use SolveBeam\Twinfield\LedgerAccount\LedgerAccountType;
use SolveBeam\Twinfield\LedgerAccount\Subanalysis;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class LedgerAccountResourceTest extends TestCase
{
    public function testSearchesAPage(): void
    {
        $http = ReplayClient::jsonFixture('ledger-accounts-list');

        $page = TestTokens::restClient($http)->ledgerAccounts()->search('17*')
            ->types(LedgerAccountType::Balance, LedgerAccountType::ProfitAndLoss)
            ->modifiedSince(new DateTimeImmutable('2026-01-01T00:00:00+00:00'))
            ->orderByName(ascending: false)
            ->limit(5)
            ->get();

        self::assertCount(5, $page);
        self::assertSame(2799, $page->total);
        self::assertSame(2778, $page->filteredTotal);
        self::assertTrue($page->hasMore());
        self::assertEquals(
            new LedgerAccount(
                TestTokens::company(),
                'eaa7d23d-3182-ebbc-32f3-b62b55b1e3ba',
                '17733',
                'Hkwpbsxsqsknwegmlib cccpib nmy dqltrkepnl qx uhzetwaz isc vbjffzwf',
                LedgerAccountType::Balance,
                Subanalysis::Mandatory,
                null,
            ),
            $page->items[0],
        );
        self::assertSame(
            TestTokens::REST_COMPANY_URL . '/bookkeeping/accounts?searchPattern=17%2A&types=balance%2CprofitAndLoss'
            . '&modifiedSince=2026-01-01T00%3A00%3A00%2B00%3A00&orderBy=name&asc=false&limit=5',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testListsAllPages(): void
    {
        $http = new ReplayClient(
            ReplayClient::raw(
                200,
                (string) json_encode(
                    ['items' => [self::account('1000'), self::account('1001')], 'total' => 3, 'filteredTotal' => 3],
                ),
            ),
            ReplayClient::raw(
                200,
                (string) json_encode(['items' => [self::account('1002')], 'total' => 3, 'filteredTotal' => 3]),
            ),
        );

        $codes = array_map(
            static fn (LedgerAccount $account): string => $account->code,
            iterator_to_array(TestTokens::restClient($http)->ledgerAccounts()->search()->limit(2)->all(), false),
        );

        self::assertSame(['1000', '1001', '1002'], $codes);
        self::assertStringEndsWith('/bookkeeping/accounts?limit=2', (string) $http->requests[0]->getUri());
        self::assertStringEndsWith('/bookkeeping/accounts?limit=2&offset=2', (string) $http->requests[1]->getUri());
    }

    public function testGetsOneAccount(): void
    {
        $http = ReplayClient::jsonFixture('ledger-accounts-get');

        $account = TestTokens::restClient($http)->ledgerAccounts()->get('EAA7D23D-3182-EBBC-32F3-B62B55B1E3BA');

        self::assertSame('17733', $account->code);
        self::assertSame(
            TestTokens::REST_COMPANY_URL . '/bookkeeping/accounts/eaa7d23d-3182-ebbc-32f3-b62b55b1e3ba',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testThrowsWhenNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Requested resource was not found');

        TestTokens::restClient(new ReplayClient(ReplayClient::raw(404, '"Requested resource was not found"')))
            ->ledgerAccounts()->get('eaa7d23d-3182-ebbc-32f3-b62b55b1e3ba');
    }

    public function testRefusesACodeAsId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestTokens::restClient(new ReplayClient())->ledgerAccounts()->get('8000');
    }

    /**
     * @return array<string, mixed>
     */
    private static function account(string $code): array
    {
        return [
            'id' => '0f8fad5b-d9cb-469f-a165-70867728950e',
            'code' => $code,
            'name' => 'Account ' . $code,
            'type' => 'profitAndLoss',
            'subanalysis' => 'allowed',
            'defaultParentAccountId' => null,
        ];
    }
}
