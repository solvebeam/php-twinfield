<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration;

/**
 * A message in the result of a declarations request, e.g.
 * `Error InvalidDocumentType: Het document type van dit Document word niet ondersteund door deze functie`.
 */
final readonly class DeclarationMessage
{
    /**
     * @param list<string|null> $parameters E.g. `['modifiedSince']`; null for `<string xsi:nil="true"/>`.
     */
    public function __construct(
        public DeclarationMessageType $type,
        /** Use this to act on a message. */
        public DeclarationLoadMessage|DeclarationStatusMessage $code,
        /** Readable text, in the language of the user. Only for logging or showing to the user. */
        public string $text,
        public array $parameters,
    ) {
    }
}
