<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use Psr\Http\Message\ResponseInterface;

/**
 * The rate limit state Twinfield reports in the `X-RateLimit-…` headers of one response, in API
 * credits per minute. Read it after a request with `Twinfield::lastRateLimit()`.
 *
 * A value is `null` when Twinfield did not send the header. That is valid Twinfield behaviour: a 429
 * for the concurrency limit has none of these headers, and neither have responses that do not come
 * from the API itself (e.g. a gateway error). The numbers may not be completely accurate, Twinfield
 * warns, because of its distributed infrastructure.
 *
 * @see https://developers.twinfield.com/documentation/getting-started/fair-use-policy#rate-limits
 */
final readonly class RateLimit
{
    /**
     * @param int|null $limit             `X-RateLimit-Limit`: credits per minute, the lowest limit of all buckets.
     * @param int|null $remaining         `X-RateLimit-Remaining`: credits left in the current minute, the
     *                                    lowest of all buckets.
     * @param int|null $clientIdLimit     `X-RateLimit-ClientId-Limit`: credits per minute for the client ID.
     * @param int|null $clientIdRemaining `X-RateLimit-ClientId-Remaining`: credits left in the current
     *                                    minute for the client ID.
     * @param int|null $credited          `X-RateLimit-Credited`: credits this request cost (1 for a query
     *                                    request, 3 for any other request).
     */
    public function __construct(
        public ?int $limit = null,
        public ?int $remaining = null,
        public ?int $clientIdLimit = null,
        public ?int $clientIdRemaining = null,
        public ?int $credited = null,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        return new self(
            limit: self::intHeader($response, 'X-RateLimit-Limit'),
            remaining: self::intHeader($response, 'X-RateLimit-Remaining'),
            clientIdLimit: self::intHeader($response, 'X-RateLimit-ClientId-Limit'),
            clientIdRemaining: self::intHeader($response, 'X-RateLimit-ClientId-Remaining'),
            credited: self::intHeader($response, 'X-RateLimit-Credited'),
        );
    }

    /**
     * False when the response had none of the `X-RateLimit-…` headers.
     */
    public function isKnown(): bool
    {
        return $this->limit !== null
            || $this->remaining !== null
            || $this->clientIdLimit !== null
            || $this->clientIdRemaining !== null
            || $this->credited !== null;
    }

    /**
     * A header that is missing gives `null`, and so does a value that is not a whole number of zero
     * or more. These headers are read on every response and are only informative: a value we do not
     * understand must not make a request fail that Twinfield processed, nor replace the exception of
     * a 429 or a SOAP fault.
     */
    private static function intHeader(ResponseInterface $response, string $name): ?int
    {
        $value = $response->getHeaderLine($name);

        return ctype_digit($value) ? (int) $value : null;
    }
}
