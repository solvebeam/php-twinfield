<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Matching;

use DateTimeImmutable;
use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live REST API matching audit trail (read-only) of TWINFIELD_COMPANY.
 */
final class MatchingAuditTrailTest extends IntegrationTestCase
{
    public function testReadsTheAuditTrailInOrder(): void
    {
        $this->requireScopes(Scope::ReadBookkeepingMatches);

        $events = $this->twinfield->matching()->auditTrail(new DateTimeImmutable('2015-01-01'), 50);

        if ($events === []) {
            self::markTestSkipped('The company has no matches in the audit trail.');
        }

        $previous = null;

        foreach ($events as $event) {
            self::assertNotEmpty($event->lines);

            if ($previous !== null) {
                self::assertGreaterThanOrEqual($previous, $event->logTimestamp);
            }

            $previous = $event->logTimestamp;
        }
    }

    public function testPagesThroughTheAuditTrail(): void
    {
        $this->requireScopes(Scope::ReadBookkeepingMatches);

        $ids = [];

        foreach ($this->twinfield->matching()->auditTrailSince(new DateTimeImmutable('2015-01-01')) as $event) {
            $ids[] = $event->eventId;

            if (count($ids) >= 450) {
                break;
            }
        }

        self::assertSame($ids, array_values(array_unique($ids)), 'Each event once.');
    }
}
