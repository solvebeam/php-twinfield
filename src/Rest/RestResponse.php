<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

use Psr\Http\Message\ResponseInterface;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A successful (2xx or 3xx) response of the REST API.
 *
 *   $response = $twinfield->rest('GET', '/organisations/{organisationId}/companies/{companyId}/vat/vatcodes');
 *
 *   foreach ($response->json()->list('items') as $vatCode) { … }
 */
final readonly class RestResponse
{
    public function __construct(
        public ResponseInterface $http,
    ) {
    }

    public function status(): int
    {
        return $this->http->getStatusCode();
    }

    public function header(string $name): string
    {
        return $this->http->getHeaderLine($name);
    }

    public function body(): string
    {
        return (string) $this->http->getBody();
    }

    /**
     * The body as JSON object.
     *
     * @throws UnexpectedResponseException When the body is not a JSON object.
     */
    public function json(): JsonValue
    {
        return JsonValue::decode($this->body());
    }

    /**
     * The body as JSON array of objects.
     *
     * @return list<JsonValue>
     *
     * @throws UnexpectedResponseException When the body is not a JSON array of objects.
     */
    public function jsonList(): array
    {
        return JsonValue::decodeList($this->body());
    }
}
