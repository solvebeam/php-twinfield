<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Twinfield answered HTTP 429 Too Many Requests.
 */
final class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, 429);
    }

    public static function fromHttpResponse(ResponseInterface $response): self
    {
        $retryAfter = $response->getHeaderLine('Retry-After');
        $seconds = ctype_digit($retryAfter) ? (int) $retryAfter : null;

        return new self(
            $seconds === null
                ? 'Twinfield rate limit exceeded (HTTP 429). Wait a moment and try again.'
                : sprintf('Twinfield rate limit exceeded (HTTP 429). Retry after %d seconds.', $seconds),
            $seconds,
        );
    }
}
