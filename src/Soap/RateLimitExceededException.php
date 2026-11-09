<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Twinfield answered HTTP 429 Too Many Requests: the rate limit or the concurrency limit is exceeded.
 *
 * The header values are `null` when Twinfield did not send the header. `Retry-After` is only sent
 * when the rate limit is exceeded, and Twinfield sends no headers for the concurrency limit.
 *
 * @see https://developers.twinfield.com/documentation/getting-started/fair-use-policy#rate-limits
 */
final class RateLimitExceededException extends RuntimeException
{
    /**
     * `Limit` and `Remaining` are API credits per minute; without `ClientId` the lowest of all buckets.
     *
     * @param int|null $retryAfterSeconds          `Retry-After`: the recommended wait in seconds.
     * @param int|null $rateLimitLimit             `X-RateLimit-Limit`
     * @param int|null $rateLimitRemaining         `X-RateLimit-Remaining`
     * @param int|null $rateLimitClientIdLimit     `X-RateLimit-ClientId-Limit`
     * @param int|null $rateLimitClientIdRemaining `X-RateLimit-ClientId-Remaining`
     * @param int|null $rateLimitCredited          `X-RateLimit-Credited`: API credits this request cost.
     * @param bool     $concurrencyLimitExceeded   The response says "Concurrency limit exceeded.".
     */
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?int $rateLimitLimit = null,
        public readonly ?int $rateLimitRemaining = null,
        public readonly ?int $rateLimitClientIdLimit = null,
        public readonly ?int $rateLimitClientIdRemaining = null,
        public readonly ?int $rateLimitCredited = null,
        public readonly bool $concurrencyLimitExceeded = false,
    ) {
        parent::__construct($message, 429);
    }

    public static function fromHttpResponse(ResponseInterface $response): self
    {
        $seconds = self::intHeader($response, 'Retry-After');
        $concurrency = stripos((string) $response->getBody(), 'Concurrency limit exceeded') !== false;

        $message = match (true) {
            $concurrency => 'Twinfield concurrency limit exceeded (HTTP 429). Send fewer requests at the same time.',
            $seconds === null => 'Twinfield rate limit exceeded (HTTP 429). Wait a moment and try again.',
            default => sprintf('Twinfield rate limit exceeded (HTTP 429). Retry after %d seconds.', $seconds),
        };

        return new self(
            $message,
            retryAfterSeconds: $seconds,
            rateLimitLimit: self::intHeader($response, 'X-RateLimit-Limit'),
            rateLimitRemaining: self::intHeader($response, 'X-RateLimit-Remaining'),
            rateLimitClientIdLimit: self::intHeader($response, 'X-RateLimit-ClientId-Limit'),
            rateLimitClientIdRemaining: self::intHeader($response, 'X-RateLimit-ClientId-Remaining'),
            rateLimitCredited: self::intHeader($response, 'X-RateLimit-Credited'),
            concurrencyLimitExceeded: $concurrency,
        );
    }

    private static function intHeader(ResponseInterface $response, string $name): ?int
    {
        $value = $response->getHeaderLine($name);

        return ctype_digit($value) ? (int) $value : null;
    }
}
