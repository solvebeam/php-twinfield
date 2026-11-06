<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use Psr\Http\Client\ClientInterface;
use RuntimeException;

final class SoapClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly SoapRequestFactory $requestFactory,
    ) {
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
