<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Offline PSR-18 client that answers with fixtures from `tests/Fixtures/soap/` (SOAP) and
 * `tests/Fixtures/rest/` (REST API).
 * Keeps the sent requests so tests can assert on them.
 */
final class ReplayClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $responses;

    public function __construct(ResponseInterface ...$responses)
    {
        $this->responses = array_values($responses);
    }

    public static function fixture(string $name, int $status = 200): self
    {
        return new self(self::response($name, $status));
    }

    public static function response(string $name, int $status = 200): ResponseInterface
    {
        $file = dirname(__DIR__, 2) . '/Fixtures/soap/' . $name . '.xml';

        if (!is_readable($file)) {
            throw new RuntimeException(sprintf('Fixture "%s" not found.', $file));
        }

        return new Response($status, ['Content-Type' => 'text/xml; charset=utf-8'], (string) file_get_contents($file));
    }

    /**
     * Response of the REST API from `tests/Fixtures/rest/{name}.json`.
     *
     * @param array<string, string> $headers
     */
    public static function jsonFixture(string $name, int $status = 200, array $headers = []): self
    {
        return new self(self::jsonResponse($name, $status, $headers));
    }

    /**
     * @param array<string, string> $headers
     */
    public static function jsonResponse(string $name, int $status = 200, array $headers = []): ResponseInterface
    {
        $file = dirname(__DIR__, 2) . '/Fixtures/rest/' . $name . '.json';

        if (!is_readable($file)) {
            throw new RuntimeException(sprintf('Fixture "%s" not found.', $file));
        }

        return new Response(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'] + $headers,
            (string) file_get_contents($file),
        );
    }

    /**
     * Response with a literal body, e.g. an empty 204 or an error.
     *
     * @param array<string, string> $headers
     */
    public static function raw(int $status, string $body = '', array $headers = []): ResponseInterface
    {
        return new Response($status, $headers, $body);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return array_shift($this->responses) ?? throw new RuntimeException(sprintf(
            'ReplayClient has no response left for %s %s.',
            $request->getMethod(),
            $request->getUri(),
        ));
    }

    public function lastRequest(): RequestInterface
    {
        $request = end($this->requests);

        return $request !== false ? $request : throw new RuntimeException('No request was sent.');
    }
}
