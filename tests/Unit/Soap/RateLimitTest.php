<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Soap;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Soap\LastRateLimit;
use SolveBeam\Twinfield\Soap\RateLimit;

final class RateLimitTest extends TestCase
{
    public function testReadsTheHeadersOfAResponse(): void
    {
        $rateLimit = RateLimit::fromResponse(new Response(200, [
            'X-RateLimit-Limit' => '500',
            'X-RateLimit-Remaining' => '497',
            'X-RateLimit-ClientId-Limit' => '1000',
            'X-RateLimit-ClientId-Remaining' => '940',
            'X-RateLimit-Credited' => '3',
        ]));

        self::assertSame(500, $rateLimit->limit);
        self::assertSame(497, $rateLimit->remaining);
        self::assertSame(1000, $rateLimit->clientIdLimit);
        self::assertSame(940, $rateLimit->clientIdRemaining);
        self::assertSame(3, $rateLimit->credited);
        self::assertTrue($rateLimit->isKnown());
    }

    public function testHeaderNamesAreCaseInsensitive(): void
    {
        $rateLimit = RateLimit::fromResponse(new Response(200, ['x-ratelimit-remaining' => '0']));

        self::assertSame(0, $rateLimit->remaining);
        self::assertTrue($rateLimit->isKnown());
    }

    public function testAMissingHeaderIsNull(): void
    {
        $rateLimit = RateLimit::fromResponse(new Response(200, ['X-RateLimit-Credited' => '1']));

        self::assertNull($rateLimit->limit);
        self::assertNull($rateLimit->remaining);
        self::assertNull($rateLimit->clientIdLimit);
        self::assertNull($rateLimit->clientIdRemaining);
        self::assertSame(1, $rateLimit->credited);
        self::assertTrue($rateLimit->isKnown());
    }

    public function testAResponseWithoutHeadersIsNotKnown(): void
    {
        self::assertFalse(RateLimit::fromResponse(new Response(200))->isKnown());
    }

    public function testAValueThatIsNotAWholeNumberIsNull(): void
    {
        $rateLimit = RateLimit::fromResponse(new Response(200, [
            'X-RateLimit-Limit' => 'unlimited',
            'X-RateLimit-Remaining' => '-1',
            'X-RateLimit-ClientId-Limit' => '1000.5',
            'X-RateLimit-ClientId-Remaining' => '',
            'X-RateLimit-Credited' => '1',
        ]));

        self::assertEquals(new RateLimit(credited: 1), $rateLimit);
    }

    public function testLastRateLimitIsNullBeforeTheFirstResponse(): void
    {
        self::assertNull((new LastRateLimit())->get());
    }

    public function testLastRateLimitKeepsTheMostRecentResponseWithHeaders(): void
    {
        $last = new LastRateLimit();

        $last->record(new Response(200, ['X-RateLimit-Remaining' => '499']));
        $last->record(new Response(200, ['X-RateLimit-Remaining' => '496', 'X-RateLimit-Credited' => '3']));
        $last->record(new Response(502));

        self::assertEquals(new RateLimit(remaining: 496, credited: 3), $last->get());
    }
}
