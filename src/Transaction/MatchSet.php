<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Lines that are matched with each other, e.g. a payment and the invoice it paid.
 * Only in responses: you cannot match in a transaction request.
 */
final readonly class MatchSet
{
    /**
     * @param list<MatchLine> $lines
     */
    public function __construct(
        /** Null when Twinfield leaves the status out. */
        public ?TransactionStatus $status,
        public DateTimeImmutable $matchDate,
        public Amount $matchValue,
        public array $lines,
    ) {
    }

    public static function fromXml(XmlElement $set): self
    {
        $status = $set->element->hasAttribute('status')
            ? $set->enumAttribute('status', TransactionStatus::class)
            : null;

        return new self(
            status: $status,
            matchDate: TransactionXml::date($set, 'matchdate'),
            matchValue: TransactionXml::amount($set, 'matchvalue'),
            lines: array_map(MatchLine::fromXml(...), $set->child('lines')->list('line')),
        );
    }

    /**
     * Match sets of a line; none when it is not matched (Twinfield then leaves `<matches>` out).
     *
     * @return list<self>
     */
    public static function listFromLine(XmlElement $line): array
    {
        $matches = $line->nullableChild('matches');

        return $matches === null ? [] : array_map(self::fromXml(...), $matches->list('set'));
    }
}
