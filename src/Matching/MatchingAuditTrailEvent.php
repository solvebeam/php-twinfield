<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * An event of the matching audit trail (REST API): transaction lines that were matched, e.g. an
 * invoice and its payment. Read with `matching()->auditTrail()`.
 */
final readonly class MatchingAuditTrailEvent
{
    /**
     * @param list<MatchingAuditTrailLine> $lines
     */
    public function __construct(
        public CompanyReference $company,
        /** GUID of the event: unique, use it to skip events you already have. */
        public string $eventId,
        public MatchingEventType $eventType,
        /** When Twinfield logged the event; the audit trail is in this order. */
        public DateTimeImmutable $logTimestamp,
        /** When the event happened. */
        public DateTimeImmutable $eventTimestamp,
        /** Code of the user, e.g. `JDOE`. */
        public string $userCode,
        /** GUID of the match. */
        public string $matchId,
        /** Match level: 1 for customers and suppliers, 2 for other dimensions. */
        public int $matchLevel,
        /** Code of the dimension the lines are matched on, e.g. customer `1000`. */
        public string $dimensionCode,
        /** Match number, as in browse data (`fin.trs.line.matchnumber`) and for undoing the match. */
        public int $matchNumber,
        /** Date of the match (without time); null for an Unmatched event. */
        public ?DateTimeImmutable $matchDate,
        public array $lines,
    ) {
        Guid::of($eventId, 'Event ID');

        if (($matchDate === null) !== ($eventType === MatchingEventType::Unmatched)) {
            throw new InvalidArgumentException('Only an Unmatched event has no match date.');
        }

        Guid::of($matchId, 'Match ID');
    }
}
