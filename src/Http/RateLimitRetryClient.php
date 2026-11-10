<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Http;

use Closure;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SolveBeam\Twinfield\Soap\RateLimitExceededException;

/**
 * PSR-18 client that waits and sends a request again when Twinfield answers HTTP 429 Too Many
 * Requests, with the exponential backoff Twinfield recommends: the first wait is `Retry-After`
 * and every next wait is doubled. Wrap your own HTTP client in it:
 *
 *   $twinfield = Twinfield::create(
 *       $accessTokenProvider,
 *       new RateLimitRetryClient(Psr18ClientDiscovery::find()),
 *   );
 *
 * Sending again is safe for every request, also one that creates or updates something: Twinfield
 * does not process a request it answers with 429. Other errors are never retried.
 *
 * When the attempts or the waiting time run out, the 429 response is returned as it is, so the SDK
 * throws its `RateLimitExceededException`.
 *
 * @see https://developers.twinfield.com/documentation/getting-started/fair-use-policy#rate-limits
 */
final readonly class RateLimitRetryClient implements ClientInterface
{
    /** @var Closure(int): void */
    private Closure $sleep;

    /**
     * @param int                       $maxAttempts    How often a request is sent at most, the first time
     *                                                  included.
     * @param int                       $maxWaitSeconds How long one request waits at most, all its waits
     *                                                  together. A wait that no longer fits is not started.
     * @param (Closure(int): void)|null $sleep          Waits the given number of seconds; `sleep()` by default.
     */
    public function __construct(
        private ClientInterface $httpClient,
        private int $maxAttempts = 5,
        private int $maxWaitSeconds = 120,
        ?Closure $sleep = null,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException(
                sprintf('The maximum number of attempts must be at least 1, got %d.', $maxAttempts),
            );
        }

        if ($maxWaitSeconds < 0) {
            throw new InvalidArgumentException(
                sprintf('The maximum wait must be 0 seconds or more, got %d.', $maxWaitSeconds),
            );
        }

        $this->sleep = $sleep ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $waited = 0;
        $factor = 1;

        for ($attempt = 1;; $attempt++) {
            $response = $this->httpClient->sendRequest($request);

            if ($response->getStatusCode() !== 429 || $attempt === $this->maxAttempts) {
                return $response;
            }

            // Start at Retry-After and double the wait on every next attempt.
            // A 429 for the concurrency limit has no Retry-After: start at 1 second.
            $retryAfter = RateLimitExceededException::fromHttpResponse($response)->retryAfterSeconds ?? 1;
            $seconds = $retryAfter * $factor;

            $body = $request->getBody();

            // A body that cannot be rewound cannot be sent a second time.
            if ($waited + $seconds > $this->maxWaitSeconds || !$body->isSeekable()) {
                return $response;
            }

            ($this->sleep)($seconds);

            $waited += $seconds;
            $factor *= 2;

            $body->rewind();
        }
    }
}
