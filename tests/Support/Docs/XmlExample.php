<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Docs;

/**
 * One call of an example with the XML the SDK sends and the XML Twinfield returns, for the
 * "XML" section of the example's README.
 *
 * The code runs offline: `$twinfield` answers with the fixtures from `tests/Fixtures/soap/`,
 * one per request. The section shows the last request and its response, or the request `$request`.
 */
final readonly class XmlExample
{
    /**
     * @param string $title Heading, named after the section in the Twinfield documentation.
     * @param string $documentation Link to that section in the Twinfield documentation.
     * @param string $code PHP code that makes the call, with `$twinfield` for a client of company 1000.
     * @param list<string> $fixtures Responses from `tests/Fixtures/soap/`, one per request.
     * @param bool $showResponse False when the fixture is only there to let the call finish, and
     *                           does not belong to the request; `$responseNote` then explains it.
     * @param int|null $limit Show at most this many elements with the same name in a list, e.g.
     *                        the first two of many deleted transactions.
     * @param int|null $request Index of the request to show, with its fixture, when it is not the last
     *                          one: e.g. the save of a call that reads the result back afterwards.
     */
    public function __construct(
        public string $title,
        public string $documentation,
        public string $code,
        public array $fixtures,
        public bool $showResponse = true,
        public ?string $responseNote = null,
        public ?int $limit = null,
        public ?int $request = null,
    ) {
    }
}
