<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Http;

use Closure;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SolveBeam\Twinfield\Authentication\AuthenticationHeader;
use SolveBeam\Twinfield\Finder\Search;
use SolveBeam\Twinfield\Http\RateLimitRetryClient;
use SolveBeam\Twinfield\Soap\Psr17SoapRequestFactory;
use SolveBeam\Twinfield\Soap\RateLimitExceededException;
use SolveBeam\Twinfield\Soap\SoapClient;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;

final class RateLimitRetryClientTest extends TestCase
{
    /** @var list<int> */
    private array $waits = [];

    public function testWaitsForRetryAfterAndSendsTheRequestAgain(): void
    {
        $http = new ReplayClient($this->rateLimited(2), new Response(200, [], 'ok'));
        // Read the body like a real HTTP client does, so the second attempt needs a rewind.
        $reading = new class ($http) implements ClientInterface {
            /** @var list<string> */
            public array $bodies = [];

            public function __construct(private readonly ReplayClient $http)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->bodies[] = $request->getBody()->getContents();

                return $this->http->sendRequest($request);
            }
        };

        $client = new RateLimitRetryClient($reading, sleep: $this->sleep());
        $response = $client->sendRequest(new Request('POST', 'https://example.test/', [], '<request/>'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([2], $this->waits);
        self::assertSame(['<request/>', '<request/>'], $reading->bodies);
    }

    public function testDoublesTheWaitOnEveryNextAttempt(): void
    {
        $http = new ReplayClient(
            $this->rateLimited(2),
            $this->rateLimited(2),
            $this->rateLimited(2),
            new Response(200),
        );

        $response = $this->client($http)->sendRequest($this->request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([2, 4, 8], $this->waits);
        self::assertCount(4, $http->requests);
    }

    public function testStartsAtOneSecondWithoutRetryAfter(): void
    {
        $concurrency = new Response(429, [], 'Concurrency limit exceeded.');
        $http = new ReplayClient($concurrency, $concurrency, $concurrency, new Response(200));

        $this->client($http)->sendRequest($this->request());

        self::assertSame([1, 2, 4], $this->waits);
    }

    public function testReturnsTheLast429AfterTheMaximumNumberOfAttempts(): void
    {
        $http = new ReplayClient($this->rateLimited(1), $this->rateLimited(1), $this->rateLimited(1));

        $response = $this->client($http, maxAttempts: 3)->sendRequest($this->request());

        self::assertSame(429, $response->getStatusCode());
        self::assertSame([1, 2], $this->waits);
        self::assertCount(3, $http->requests);
    }

    public function testTheSdkThrowsWhenTheAttemptsRunOut(): void
    {
        $http = new ReplayClient($this->rateLimited(30), $this->rateLimited(30));
        $factory = new Psr17Factory();
        $soap = new SoapClient(
            $this->client($http, maxAttempts: 2),
            new Psr17SoapRequestFactory($factory, $factory),
        );

        try {
            $soap->send(
                'https://example.test/webservices/finder.asmx',
                new Search('DIM', '*', 0, 1, 10),
                new AuthenticationHeader('token', '1000'),
            );
            self::fail('Expected a RateLimitExceededException.');
        } catch (RateLimitExceededException $exception) {
            self::assertSame(30, $exception->retryAfterSeconds);
            self::assertSame([30], $this->waits);
            self::assertCount(2, $http->requests);
        }
    }

    public function testDoesNotStartAWaitThatExceedsTheMaximumWait(): void
    {
        // 30 seconds fit in 80, the next 60 seconds do not.
        $http = new ReplayClient($this->rateLimited(30), $this->rateLimited(30), new Response(200));

        $response = $this->client($http, maxWaitSeconds: 80)->sendRequest($this->request());

        self::assertSame(429, $response->getStatusCode());
        self::assertSame([30], $this->waits);
        self::assertCount(2, $http->requests);
    }

    public function testDoesNotRetryWithAMaximumWaitOfZero(): void
    {
        $http = new ReplayClient($this->rateLimited(1), new Response(200));

        $response = $this->client($http, maxWaitSeconds: 0)->sendRequest($this->request());

        self::assertSame(429, $response->getStatusCode());
        self::assertSame([], $this->waits);
    }

    public function testDoesNotRetryOtherResponses(): void
    {
        foreach ([200, 401, 500, 503] as $status) {
            $http = new ReplayClient(new Response($status), new Response(200));

            $response = $this->client($http)->sendRequest($this->request());

            self::assertSame($status, $response->getStatusCode());
            self::assertCount(1, $http->requests);
        }

        self::assertSame([], $this->waits);
    }

    public function testRejectsLessThanOneAttempt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RateLimitRetryClient(new ReplayClient(), maxAttempts: 0);
    }

    public function testRejectsANegativeMaximumWait(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RateLimitRetryClient(new ReplayClient(), maxWaitSeconds: -1);
    }

    private function client(ReplayClient $http, int $maxAttempts = 5, int $maxWaitSeconds = 120): RateLimitRetryClient
    {
        return new RateLimitRetryClient($http, $maxAttempts, $maxWaitSeconds, $this->sleep());
    }

    /**
     * @return Closure(int): void
     */
    private function sleep(): Closure
    {
        return function (int $seconds): void {
            $this->waits[] = $seconds;
        };
    }

    private function request(): Request
    {
        return new Request('POST', 'https://example.test/webservices/finder.asmx', [], '<request/>');
    }

    private function rateLimited(int $retryAfter): ResponseInterface
    {
        return new Response(429, ['Retry-After' => (string) $retryAfter], 'Too Many Requests');
    }
}
