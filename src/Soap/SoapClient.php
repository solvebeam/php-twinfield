<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use Psr\Http\Client\ClientInterface;
use RuntimeException;

/**
 * Sends SOAP requests to the Twinfield web services.
 *
 * @internal Use `Twinfield::processXml()`, `Twinfield::query()` and `Twinfield::process()`.
 */
final readonly class SoapClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private SoapRequestFactory $requestFactory,
        private LastRateLimit $lastRateLimit = new LastRateLimit(),
    ) {
    }

    /**
     * The rate limit headers of the most recent response that had them, also when that response was
     * a SOAP fault or an HTTP error; null before the first one.
     */
    public function lastRateLimit(): ?RateLimit
    {
        return $this->lastRateLimit->get();
    }

    public function send(
        string $url,
        object $body,
        object|null $header = null,
        string $method = 'POST',
    ): SoapResponse {
        $envelope = new SoapEnvelope($body, $header);

        return $this->sendEnvelope($url, $envelope, $method);
    }

    public function sendEnvelope(
        string $url,
        SoapMessage $envelope,
        string $method = 'POST',
    ): SoapResponse {
        $request = $envelope->toRequest($url, $this->requestFactory, $method);
        $response = $this->httpClient->sendRequest($request);

        // Before anything is thrown: a fault or an HTTP error costs API credits too.
        $this->lastRateLimit->record($response);

        if ($response->getStatusCode() === 429) {
            throw RateLimitExceededException::fromHttpResponse($response);
        }

        try {
            $soapResponse = SoapResponse::fromHttpResponse($response);
        } catch (RuntimeException $exception) {
            // Authorization errors come as HTTP 401/403, not always with a SOAP fault.
            if (in_array($response->getStatusCode(), [401, 403], true)) {
                throw AccessDeniedException::fromHttpResponse($response, $exception);
            }

            throw $exception;
        }

        if ($soapResponse->isFault()) {
            throw SoapFaultException::fromSoapResponse($soapResponse);
        }

        return $soapResponse;
    }
}
