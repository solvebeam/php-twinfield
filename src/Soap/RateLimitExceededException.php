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
 * The `rateLimit…` properties are the values of `$rateLimit`, the same object that
 * `Twinfield::lastRateLimit()` returns after any request.
 *
 * @see https://developers.twinfield.com/documentation/getting-started/fair-use-policy#rate-limits
 */
final class RateLimitExceededException extends RuntimeException
{
    /**
     * The `X-RateLimit-…` headers of the 429 response.
     */
    public readonly RateLimit $rateLimit;

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

        $this->rateLimit = new RateLimit(
            $rateLimitLimit,
            $rateLimitRemaining,
            $rateLimitClientIdLimit,
            $rateLimitClientIdRemaining,
            $rateLimitCredited,
        );
    }

    /**
     * @internal
     */
    public static function fromHttpResponse(ResponseInterface $response): self
    {
        // Retry-After may also be an HTTP date (RFC 9110); Twinfield documents seconds, anything else is null.
        $retryAfter = $response->getHeaderLine('Retry-After');
        $seconds = ctype_digit($retryAfter) ? (int) $retryAfter : null;
        $rateLimit = RateLimit::fromResponse($response);
        $concurrency = stripos((string) $response->getBody(), 'Concurrency limit exceeded') !== false;

        $message = match (true) {
            $concurrency => 'Twinfield concurrency limit exceeded (HTTP 429). Send fewer requests at the same time.',
            $seconds === null => 'Twinfield rate limit exceeded (HTTP 429). Wait a moment and try again.',
            default => sprintf('Twinfield rate limit exceeded (HTTP 429). Retry after %d seconds.', $seconds),
        };

        return new self(
            $message,
            retryAfterSeconds: $seconds,
            rateLimitLimit: $rateLimit->limit,
            rateLimitRemaining: $rateLimit->remaining,
            rateLimitClientIdLimit: $rateLimit->clientIdLimit,
            rateLimitClientIdRemaining: $rateLimit->clientIdRemaining,
            rateLimitCredited: $rateLimit->credited,
            concurrencyLimitExceeded: $concurrency,
        );
    }
}
