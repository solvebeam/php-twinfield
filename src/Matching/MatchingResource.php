<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Match existing transaction lines with each other (afletteren), e.g. a sales invoice and its
 * payment, and undo a match.
 *
 *   $matching = $twinfield->company('1000')->matching();
 *
 *   $matching->match(
 *       MatchInput::customers(new DateTimeImmutable('2026-11-10'))
 *           ->line('KAS', 202600014, 2)
 *           ->line('VRK', 202600070, 1, matchValue: '119.00'),
 *   );
 *
 *   $matching->undo(DimensionTypeCode::AccountsReceivable, '1000', 12);
 *
 * Matching is a request on the XML web services (`<match>`), undoing one is UndoMatch on
 * matching.asmx. The match number for undo() is in browse data (`fin.trs.line.matchnumber`).
 * The matching audit trail (auditTrail()) is on the REST API.
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/matching/
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/undo-match/
 */
final readonly class MatchingResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Matches the lines of the set, in the company of the client.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. for a line that is already matched.
     * @throws LogicException When the set has fewer than two lines.
     */
    public function match(MatchInput $input): void
    {
        $company = $this->twinfield->companyReference();

        $match = XmlElement::root($this->twinfield->processXml($input->toXml($company->code)));

        if ($match->element->localName !== 'match') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a <match> response, got <%s>.',
                $match->element->localName,
            ));
        }
    }

    /**
     * Undoes match set `$matchNumber` of a dimension, e.g. customer 1000.
     *
     * @param DimensionTypeCode|string $dimensionType E.g. `DimensionTypeCode::AccountsReceivable`
     *                                                for a customer; see MatchCode::dimensionType().
     * @param string $dimension Code of the customer, supplier, project or account.
     *
     * @throws MatchNotFoundException When the dimension has no match set with this number.
     * @throws MatchingException E.g. DimensionNotFoundByType for an unknown dimension.
     */
    public function undo(DimensionTypeCode|string $dimensionType, string $dimension, int $matchNumber): void
    {
        $dimensionType = $dimensionType instanceof DimensionTypeCode ? $dimensionType->value : $dimensionType;

        if ($dimensionType === '' || $dimension === '') {
            throw new InvalidArgumentException('Dimension type and dimension must not be empty.');
        }

        if ($matchNumber < 1) {
            throw new InvalidArgumentException(sprintf('Match number must be 1 or more, got %d.', $matchNumber));
        }

        $office = $this->twinfield->companyCode()
            ?? throw new LogicException('Matching needs a company in the request header: use company() first.');

        $response = $this->twinfield->call(
            'matching',
            new Request\UndoMatch($office, $dimensionType, $dimension, $matchNumber),
        );

        $payload = $response->payloadElement();

        if ($payload === null || $payload->localName !== 'UndoMatchResponse') {
            throw new UnexpectedResponseException(sprintf(
                'Expected <UndoMatchResponse>, got %s.',
                $payload === null ? 'an empty SOAP body' : '<' . $payload->localName . '>',
            ));
        }

        $result = (new XmlElement($payload, 'UndoMatchResponse'))->child('UndoMatchResult');

        $errors = array_values(array_filter(
            array_map(self::message(...), $result->list('MessageOfUndoSetByTypeResult')),
            static fn (UndoMatchMessage $message): bool => $message->type === UndoMatchMessageType::Error,
        ));

        if ($errors === []) {
            return;
        }

        if ($errors[0]->code === UndoMatchMessageCode::MatchNotFound) {
            throw new MatchNotFoundException($errors);
        }

        throw new MatchingException($errors);
    }

    private static function message(XmlElement $message): UndoMatchMessage
    {
        return new UndoMatchMessage(
            type: $message->enum('Type', UndoMatchMessageType::class),
            code: $message->enum('Code', UndoMatchMessageCode::class),
            text: $message->string('Text'),
            parameters: array_map(self::parameter(...), $message->child('Parameters')->list('string')),
        );
    }

    /**
     * Null for `<string xsi:nil="true"/>`.
     */
    private static function parameter(XmlElement $parameter): ?string
    {
        $nil = $parameter->element->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'nil');

        return $nil === 'true' ? null : $parameter->text();
    }

    /**
     * One page of the matching audit trail (REST API): matches logged on or after `$since`, oldest
     * first. Scope `readBookkeepingMatches`.
     *
     *   $since = new DateTimeImmutable('-1 day');
     *
     *   foreach ($twinfield->company('1000')->matching()->auditTrail($since) as $event) {
     *       echo $event->matchNumber, ' ', $event->dimensionCode, PHP_EOL;
     *   }
     *
     * `$since` is inclusive, so the next page starts at the `logTimestamp` of the last event and repeats
     * it: auditTrailSince() pages through the trail for you and skips those.
     *
     * @param int $limit 1 to 200; Twinfield gives 50 without a limit.
     *
     * @return list<MatchingAuditTrailEvent>
     *
     * @see https://api.accounting.twinfield.com/Api/swagger/ui/index#!/Bookkeeping32Audit32Trails
     */
    public function auditTrail(DateTimeInterface $since, int $limit = 200): array
    {
        if ($limit < 1 || $limit > 200) {
            throw new InvalidArgumentException(sprintf('Limit must be 1 to 200, %d given.', $limit));
        }

        $json = $this->twinfield->rest(
            'GET',
            '/organisations/{organisationId}/companies/{companyId}/bookkeeping/audittrails/matching',
            ['since' => DateTimeImmutable::createFromInterface($since)->format('Y-m-d\TH:i:s.uP'), 'limit' => $limit],
        )->json();

        $company = $this->twinfield->companyReference();

        return array_map(
            static fn (JsonValue $event): MatchingAuditTrailEvent => self::event($event, $company),
            $json->list('items'),
        );
    }

    /**
     * All matches logged on or after `$since`, over all pages, each event once.
     *
     * @return Generator<int, MatchingAuditTrailEvent>
     */
    public function auditTrailSince(DateTimeInterface $since): Generator
    {
        $seen = [];

        while (true) {
            $events = $this->auditTrail($since);
            $new = array_values(array_filter(
                $events,
                static fn (MatchingAuditTrailEvent $event): bool => !isset($seen[$event->eventId]),
            ));

            yield from $new;

            if (count($events) < 200 || $new === []) {
                return;
            }

            $since = end($events)->logTimestamp;
            $seen = [];

            foreach ($events as $event) {
                if ($event->logTimestamp == $since) {
                    $seen[$event->eventId] = true;
                }
            }
        }
    }

    private static function event(JsonValue $event, CompanyReference $company): MatchingAuditTrailEvent
    {
        $type = $event->enum('eventType', MatchingEventType::class);
        // Unmatched events have no match date.
        $date = $type === MatchingEventType::Unmatched ? null : $event->object('matchDate');

        return new MatchingAuditTrailEvent(
            company: $company,
            eventId: $event->uuid('eventId'),
            eventType: $type,
            logTimestamp: $event->timestamp('logTimestamp'),
            eventTimestamp: $event->timestamp('eventTimestamp'),
            userCode: $event->string('userCode'),
            matchId: $event->uuid('matchId'),
            matchLevel: $event->int('matchLevel'),
            dimensionCode: $event->string('dimensionCode'),
            matchNumber: $event->int('matchNumber'),
            matchDate: $date === null ? null : new DateTimeImmutable(
                sprintf('%04d-%02d-%02d', $date->int('year'), $date->int('month'), $date->int('day')),
            ),
            lines: array_map(
                static fn (JsonValue $line): MatchingAuditTrailLine => new MatchingAuditTrailLine(
                    dayBook: $line->string('transactionTypeCode'),
                    number: $line->int('transactionNumber'),
                    lineId: $line->int('transactionLineId'),
                    matchedAmount: $line->amount('matchedAmount'),
                    matchedAmountTransactionCurrency: $line->amount('matchedAmountTransactionCurrency'),
                    matchedAmountReportingCurrency: $line->amount('matchedAmountReportingCurrency'),
                ),
                $event->list('transactionLines'),
            ),
        );
    }
}
