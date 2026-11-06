<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Http;

use DOMDocument;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Prints every request and response (pretty-printed XML) to a stream,
 * STDERR by default. Enable with `TWINFIELD_DEBUG=1`.
 *
 * Access tokens are masked.
 */
final readonly class DebugClient implements ClientInterface
{
    /** @var resource */
    private mixed $stream;

    /**
     * @param resource|null $stream
     */
    public function __construct(
        private ClientInterface $inner,
        mixed $stream = null,
    ) {
        $this->stream = $stream ?? STDERR;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->write(sprintf(
            "\n>>> %s %s\nSOAPAction: %s\n\n%s\n",
            $request->getMethod(),
            $request->getUri(),
            $request->getHeaderLine('SOAPAction'),
            self::formatBody($request),
        ));

        $start = hrtime(true);
        $response = $this->inner->sendRequest($request);
        $milliseconds = (hrtime(true) - $start) / 1e6;

        $this->write(sprintf(
            "\n<<< HTTP %d %s (%.0f ms)\n\n%s\n",
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $milliseconds,
            self::formatBody($response),
        ));

        return $response;
    }

    public static function mask(string $body): string
    {
        return (string) preg_replace(
            '~(<(?:\w+:)?AccessToken>)[^<]*(</(?:\w+:)?AccessToken>)~',
            '$1***$2',
            $body,
        );
    }

    private static function formatBody(MessageInterface $message): string
    {
        $body = (string) $message->getBody();
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        if ($body !== '' && @$document->loadXML($body) !== false) {
            $body = (string) $document->saveXML($document->documentElement);
        }

        return self::mask($body);
    }

    private function write(string $text): void
    {
        fwrite($this->stream, $text);
    }
}
