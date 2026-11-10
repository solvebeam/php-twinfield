<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

/**
 * A message in the result of a hierarchies request, e.g.
 * `Error InvalidCode: De rapportagestructuur DOESNOTEXIST bestaat niet.`
 */
final readonly class HierarchyMessage
{
    /**
     * @param list<string|null> $parameters E.g. `['DOESNOTEXIST']`; null for `<string xsi:nil="true"/>`.
     */
    public function __construct(
        public HierarchyMessageType $type,
        /** Use this to act on a message. */
        public HierarchyMessageCode $code,
        /** Readable text, in the language of the user. Only for logging or showing to the user. */
        public string $text,
        public array $parameters,
        /** Code of the node the message is about (HierarchyNodeMessage), else null. */
        public ?string $node = null,
    ) {
    }
}
