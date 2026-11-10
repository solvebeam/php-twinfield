<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SolveBeam\Twinfield\Soap\AccessDeniedException;
use SolveBeam\Twinfield\Soap\RateLimitExceededException;

/**
 * Sends requests to the Twinfield REST API (`{cluster}/Api/…`) and turns refusals into exceptions.
 * Use `Twinfield::rest()`, which adds the access token and the GUIDs of the organisation and company.
 *
 * @see https://api.accounting.twinfield.com/Api/swagger/ui/index
 */
final readonly class RestClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param string                $url     Full URL including the query string.
     * @param array<string, string> $headers E.g. `['If-Match' => '3']`.
     * @param string                $path    Path for error messages, e.g. `/vat/vatcodes`.
     *
     * @throws AccessDeniedException       HTTP 401 or 403: the access token is invalid, lacks a scope of
     *                                     the operation (e.g. `readVat`), or the role of the user has no access.
     * @throws RateLimitExceededException  HTTP 429.
     * @throws NotFoundException           HTTP 404.
     * @throws VersionConflictException    HTTP 412.
     * @throws ValidationException         HTTP 400 or 422.
     * @throws RestException               Any other 4xx or 5xx.
     */
    public function send(
        string $method,
        string $url,
        string $accessToken,
        array $headers = [],
        ?string $body = null,
        string $path = '',
    ): RestResponse {
        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->withHeader('Accept', 'application/json');

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($body));
        }

        // Headers given override the defaults, e.g. the Content-Type of a file upload.

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = $this->httpClient->sendRequest($request);

        $this->throwIfRefused($response, $method, $path === '' ? $request->getUri()->getPath() : $path);

        return new RestResponse($response);
    }

    private function throwIfRefused(ResponseInterface $response, string $method, string $path): void
    {
        $status = $response->getStatusCode();

        if ($status < 400) {
            return;
        }

        if ($status === 429) {
            throw RateLimitExceededException::fromHttpResponse($response);
        }

        if ($status === 401 || $status === 403) {
            $denied = AccessDeniedException::fromHttpResponse($response);

            throw new AccessDeniedException(
                sprintf(
                    '%s %s %s: request the scope of the operation at login (see the Swagger description,'
                    . ' e.g. readVat) and check the role of the user.',
                    $denied->getMessage(),
                    $method,
                    $path,
                ),
                $status,
            );
        }

        throw match ($status) {
            404 => NotFoundException::fromHttpResponse($response, $method, $path),
            412 => VersionConflictException::fromHttpResponse($response, $method, $path),
            400, 422 => ValidationException::fromHttpResponse($response, $method, $path),
            default => RestException::fromHttpResponse($response, $method, $path),
        };
    }
}
