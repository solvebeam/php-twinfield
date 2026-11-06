<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Twinfield answered HTTP 401 Unauthorized or 403 Forbidden without a SOAP fault.
 *
 * When the response does contain a SOAP fault, a SoapFaultException is thrown
 * instead, with e.g. ErrorCode::TokenInvalid in `detailCode`.
 *
 * @see https://developers.twinfield.com/documentation/api/types/guidelines
 */
final class AccessDeniedException extends RuntimeException
{
    public static function fromHttpResponse(ResponseInterface $response, ?Throwable $previous = null): self
    {
        // HTTP/2 responses have no reason phrase.
        $message = sprintf(
            'Twinfield denied access (HTTP %s).',
            trim($response->getStatusCode() . ' ' . $response->getReasonPhrase()),
        );

        // OAuth2 bearer errors, e.g. `Bearer error="invalid_token"`.
        $challenge = $response->getHeaderLine('WWW-Authenticate');

        if ($challenge !== '') {
            $message .= ' ' . $challenge;
        }

        return new self($message, $response->getStatusCode(), $previous);
    }
}
