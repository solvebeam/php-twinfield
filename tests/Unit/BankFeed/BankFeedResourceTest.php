<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\BankFeed;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class BankFeedResourceTest extends TestCase
{
    public function testListsPsd2Feeds(): void
    {
        // Follows the Swagger definition: the test companies have no PSD2 feeds.
        $http = ReplayClient::jsonFixture('bank-feeds-psd2');

        $feeds = TestTokens::restClient($http)->bankFeeds()->psd2()
            ->inAllCompanies()
            ->expiresBetween(null, new DateTimeImmutable('2026-12-31T00:00:00Z'))
            ->get();

        $feed = $feeds->items[0];

        self::assertSame('3f2504e0-4f89-11d3-9a0c-0305e82c3301', $feed->id);
        self::assertSame('2026-07-31', $feed->expiresOn->format('Y-m-d'));
        self::assertSame('NL91ABNA0417164300', $feed->account->iban);
        self::assertSame('1000', $feed->account->companyCode);
        self::assertSame(
            TestTokens::REST_COMPANY_URL . '/banks/feeds/psd2?requestedCompanies=all&expiresOnTo=2026-12-31T00%3A00%3A00%2B00%3A00',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testListsTheFeedsOfTheCurrentCompanyByDefault(): void
    {
        $http = new ReplayClient(ReplayClient::raw(200, '{"items":[],"total":0,"filteredTotal":0}'));

        self::assertCount(0, TestTokens::restClient($http)->bankFeeds()->psd2()->get());
        self::assertStringEndsWith('/banks/feeds/psd2?requestedCompanies=current', (string) $http->lastRequest()->getUri());
    }
}
