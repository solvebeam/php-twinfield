<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\PostingRule;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\PostingRule\PostingRuleType;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class PostingRuleResourceTest extends TestCase
{
    public function testListsPostingRules(): void
    {
        $http = ReplayClient::jsonFixture('posting-rules-list');

        $page = TestTokens::restClient($http)->postingRules()->modifiedSince(new DateTimeImmutable('2000-01-01T00:00:00Z'))->get();

        self::assertCount(4, $page);

        $rule = $page->items[1];

        self::assertSame('7fdff049-7d43-8634-4455-cad193cc1b93', $rule->id);
        self::assertSame(PostingRuleType::Sales, $rule->type);
        self::assertSame('8292e809-2d32-ff21-7699-95fdc3f0277f', $rule->accountId);
        self::assertSame('bf5d11f6-5aac-8b3f-8f8e-e80d1b09671e', $rule->currencyId);
        self::assertSame('926.00', (string) $rule->amount);
        self::assertSame('91469', $rule->description);
        self::assertSame('HXPEQ', $rule->modifiedBy);
        self::assertCount(3, $rule->lines);
        self::assertSame('78.4', $rule->lines[0]->ratio);
        self::assertNull($rule->lines[0]->costCenterId);

        // Rules without amount or currency, and an empty description.
        self::assertNull($page->items[0]->amount);
        self::assertNull($page->items[0]->currencyId);
        self::assertNull($page->items[0]->description);
        self::assertSame([], $page->items[0]->lines);

        self::assertSame(
            TestTokens::REST_COMPANY_URL . '/bookkeeping/postingrule?modifiedSince=2000-01-01T00%3A00%3A00%2B00%3A00',
            (string) $http->lastRequest()->getUri(),
        );
    }
}
