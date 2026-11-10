<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * The REST API refused a request, e.g. HTTP 400 (invalid parameters) or 409. The subclasses cover the
 * common cases: NotFoundException (404), VersionConflictException (412) and ValidationException
 * (400, 422).
 *
 * HTTP 401/403 throw a `Soap\AccessDeniedException` and HTTP 429 a `Soap\RateLimitExceededException`,
 * like the SOAP services.
 */
class RestException extends RuntimeException
{
    final public function __construct(
        string $message,
        /** HTTP status code, e.g. `422`. */
        public readonly int $status,
        /** The response body as Twinfield sent it, often JSON with the reason. */
        public readonly string $body,
    ) {
        parent::__construct($message, $status);
    }

    /**
     * @internal
     */
    public static function fromHttpResponse(ResponseInterface $response, string $method, string $path): static
    {
        $body = (string) $response->getBody();
        $status = $response->getStatusCode();
        $reason = self::reason($body);

        // HTTP/2 responses have no reason phrase.
        $message = sprintf(
            'Twinfield refused %s %s (HTTP %s)%s',
            $method,
            $path,
            trim($status . ' ' . $response->getReasonPhrase()),
            $reason === null ? '.' : ': ' . $reason,
        );

        return new static($message, $status, $body);
    }

    /**
     * The reason of an error body. The REST API sends a JSON string (`"Requested resource was not
     * found"`), or an object with a message per parameter (`{"orderBy": "The value 'x' is not valid
     * for OrderBy."}`); other bodies are shortened. Null for an empty body.
     */
    private static function reason(string $body): ?string
    {
        if (trim($body) === '') {
            return null;
        }

        $data = json_decode($body, true);

        if (is_string($data)) {
            return $data === '' ? null : $data;
        }

        if (!is_array($data)) {
            $text = trim(strip_tags($body));

            return $text === '' ? null : mb_strimwidth($text, 0, 300, '…');
        }

        $messages = [];
        $messageKeys = ['message', 'error', 'title', 'detail'];

        $collect = static function (mixed $value, int|string $key) use (&$messages, $messageKeys): void {
            if (is_string($value) && $value !== '') {
                $isParameter = is_string($key) && !in_array(strtolower($key), $messageKeys, true);
                $messages[] = $isParameter ? $key . ': ' . $value : $value;
            }
        };

        array_walk_recursive($data, $collect);

        if ($messages === []) {
            return mb_strimwidth($body, 0, 300, '…');
        }

        return implode(' ', array_values(array_unique($messages)));
    }
}
