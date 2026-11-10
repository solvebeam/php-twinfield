<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Matching;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Matching\MatchingAuditTrailEvent;
use SolveBeam\Twinfield\Matching\MatchingEventType;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class MatchingAuditTrailTest extends TestCase
{
    public function testReadsAPageOfTheAuditTrail(): void
    {
        $http = ReplayClient::jsonFixture('matching-audit-trail');

        $events = TestTokens::restClient($http)->matching()->auditTrail(new DateTimeImmutable('2015-01-01T00:00:00Z'), 2);

        self::assertCount(3, $events);
        self::assertSame(MatchingEventType::Unmatched, $events[2]->eventType);
        self::assertNull($events[2]->matchDate);

        $event = $events[0];

        self::assertSame('bb50dcff-20da-cb05-8c15-3f72e4780c15', $event->eventId);
        self::assertSame(MatchingEventType::Matched, $event->eventType);
        self::assertSame('HXPEQ', $event->userCode);
        self::assertSame(1, $event->matchLevel);
        self::assertSame('92082', $event->dimensionCode);
        self::assertSame(799, $event->matchNumber);
        self::assertSame('2026-07-20', $event->matchDate->format('Y-m-d'));
        self::assertSame('2025-07-02T10:48:25.721187', $event->logTimestamp->format('Y-m-d\TH:i:s.u'));
        self::assertCount(2, $event->lines);
        self::assertSame(640335678, $event->lines[0]->number);
        self::assertSame(3, $event->lines[0]->lineId);
        self::assertSame('-4378.50', (string) $event->lines[0]->matchedAmount);
        self::assertSame('0.00', (string) $event->lines[0]->matchedAmountReportingCurrency);
        self::assertSame(
            TestTokens::REST_COMPANY_URL . '/bookkeeping/audittrails/matching?since=2015-01-01T00%3A00%3A00.000000%2B00%3A00&limit=2',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testPagesThroughTheAuditTrailWithoutRepeatingEvents(): void
    {
        // Two events per second, so the last two events of the page share their timestamp.
        $start = new DateTimeImmutable('2026-08-03T10:00:00.5Z');
        $first = array_map(
            static fn (int $i): array => self::event($i, $start->modify(sprintf('+%d seconds', intdiv($i, 2)))->format('Y-m-d\TH:i:s.u\Z')),
            range(0, 199),
        );
        // `since` is inclusive: the next page repeats the events at the last timestamp (198 and 199).
        $last = (string) $first[199]['logTimestamp'];
        $second = [self::event(198, $last), self::event(199, $last), self::event(200, '2026-08-03T10:02:00Z')];

        $http = new ReplayClient(
            ReplayClient::raw(200, (string) json_encode(['items' => $first])),
            ReplayClient::raw(200, (string) json_encode(['items' => $second])),
        );

        $events = iterator_to_array(
            TestTokens::restClient($http)->matching()->auditTrailSince(new DateTimeImmutable('2026-08-01T00:00:00Z')),
            false,
        );

        self::assertCount(201, $events);
        self::assertSame(200, $events[200]->matchNumber);
        self::assertCount(2, $http->requests);
        self::assertStringContainsString('since=2026-08-03T10%3A01%3A39.500000%2B00%3A00', (string) $http->requests[1]->getUri());
        self::assertContainsOnlyInstancesOf(MatchingAuditTrailEvent::class, $events);
    }

    public function testRefusesALimitAbove200(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestTokens::restClient(new ReplayClient())->matching()->auditTrail(new DateTimeImmutable(), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private static function event(int $number, string $logTimestamp): array
    {
        return [
            'logTimestamp' => $logTimestamp,
            'eventTimestamp' => $logTimestamp,
            'companyId' => TestTokens::COMPANY_ID,
            'userCode' => 'JDOE',
            'eventId' => sprintf('0f8fad5b-d9cb-469f-a165-%012d', $number),
            'eventType' => 'Matched',
            'matchId' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
            'matchLevel' => 1,
            'dimensionCode' => '1000',
            'matchNumber' => $number,
            'matchDate' => ['year' => 2026, 'month' => 8, 'day' => 3],
            'transactionLines' => [],
        ];
    }
}
