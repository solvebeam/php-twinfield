<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use Psr\Http\Message\ResponseInterface;

/**
 * The rate limit of the most recent API response, shared by the SOAP and REST client and by all
 * clients of one `Twinfield::create()` (also those of `company()`), so the clients themselves stay
 * immutable.
 *
 * A response without any `X-RateLimit-…` header (a 429 for the concurrency limit, a gateway error)
 * does not replace the last known rate limit.
 *
 * @internal Use `Twinfield::lastRateLimit()`.
 */
final class LastRateLimit
{
    private ?RateLimit $rateLimit = null;

    public function record(ResponseInterface $response): void
    {
        $rateLimit = RateLimit::fromResponse($response);

        if ($rateLimit->isKnown()) {
            $this->rateLimit = $rateLimit;
        }
    }

    public function get(): ?RateLimit
    {
        return $this->rateLimit;
    }
}
