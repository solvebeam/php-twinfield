<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

/**
 * A request body that is not JSON, e.g. a file upload (multipart) or a JSON Patch document.
 *
 *   $twinfield->rest('POST', $path, body: RawBody::multipart('file', $csv, 'budgets.csv', 'text/csv'));
 */
final readonly class RawBody
{
    public function __construct(
        public string $contents,
        /** E.g. `multipart/form-data; boundary=…` or `application/json-patch+json`. */
        public string $contentType,
    ) {
    }

    /**
     * A `multipart/form-data` body with one file.
     */
    public static function multipart(
        string $field,
        string $contents,
        string $fileName,
        string $contentType,
    ): self {
        $boundary = '----twinfield-' . bin2hex(random_bytes(12));
        $fileName = str_replace(['"', "\r", "\n"], '', $fileName);

        $body = sprintf(
            "--%s\r\nContent-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\n"
            . "Content-Type: %s\r\n\r\n%s\r\n--%s--\r\n",
            $boundary,
            $field,
            $fileName,
            $contentType,
            $contents,
            $boundary,
        );

        return new self($body, 'multipart/form-data; boundary=' . $boundary);
    }
}
