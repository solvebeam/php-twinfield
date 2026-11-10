<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

/**
 * A message in the `<SearchResult>` of a Finder response, e.g.
 * `Error OptionNotAllowed: Optie foo is niet toegestaan.` with parameters `['foo']`.
 */
final readonly class FinderMessage
{
    /**
     * @param list<string> $parameters E.g. `['field', '0', '4']` for `ParameterOutOfRange`.
     */
    public function __construct(
        public FinderMessageType $type,
        /** Use this to act on a message. */
        public FinderErrorCode $code,
        /** Readable text, in the language of the user. Only for logging or showing to the user. */
        public string $text,
        public array $parameters,
    ) {
    }
}
