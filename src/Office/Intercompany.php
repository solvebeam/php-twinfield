<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Intercompany settings of the office: relations with other offices of the same organisation.
 *
 *   foreach ($office->intercompany->outgoing as $relation) {
 *       echo $relation->office->code, ' ', $relation->dimension1?->code, PHP_EOL;
 *   }
 */
final readonly class Intercompany
{
    /**
     * @param list<IntercompanyRelation> $outgoing
     * @param list<IntercompanyRelation> $incoming
     */
    public function __construct(
        public IntercompanyStatus $status,
        public array $outgoing,
        public array $incoming,
    ) {
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $intercompany): self
    {
        return new self(
            status: $intercompany->enumAttribute('status', IntercompanyStatus::class),
            outgoing: array_map(
                static fn (XmlElement $out): IntercompanyRelation => IntercompanyRelation::fromXml($out, true),
                $intercompany->child('outs')->list('out'),
            ),
            incoming: array_map(
                static fn (XmlElement $in): IntercompanyRelation => IntercompanyRelation::fromXml($in, false),
                $intercompany->child('ins')->list('in'),
            ),
        );
    }
}
