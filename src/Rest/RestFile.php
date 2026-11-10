<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

/**
 * A file of the REST API, e.g. an export of the articles as CSV or XLSX.
 *
 *   $file = $twinfield->salesArticles()->export(FileType::Xlsx);
 *   file_put_contents($file->fileName, $file->contents);
 */
final readonly class RestFile
{
    public function __construct(
        /** The bytes of the file. */
        public string $contents,
        /** E.g. `text/csv`. */
        public string $contentType,
        /** From `Content-Disposition`, e.g. `articles.csv`. */
        public string $fileName,
    ) {
    }

    /**
     * From a response with `Content-Disposition: attachment; filename=articles.csv`.
     */
    public static function fromResponse(RestResponse $response, string $defaultName): self
    {
        $disposition = $response->header('Content-Disposition');
        $name = preg_match('~filename\*?=(?:UTF-8\'\')?"?([^";]+)"?~i', $disposition, $matches) === 1
            ? basename(rawurldecode($matches[1]))
            : $defaultName;

        return new self($response->body(), $response->header('Content-Type'), $name);
    }
}
