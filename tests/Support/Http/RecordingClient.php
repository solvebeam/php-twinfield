<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Stores raw responses in `tests/Fixtures/raw/` (gitignored). Enable with
 * `TWINFIELD_RECORD=1`, then run `composer twinfield:anonymize` to turn
 * them into committable fixtures in `tests/Fixtures/soap/`.
 *
 * Files are named `{label}.xml`, `{label}-2.xml`, … per request.
 */
final class RecordingClient implements ClientInterface
{
    private int $count = 0;

    public function __construct(
        private readonly ClientInterface $inner,
        private readonly string $directory,
        private readonly string $label,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->inner->sendRequest($request);

        $this->count++;

        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Cannot create directory "%s".', $this->directory));
        }

        $suffix = $this->count === 1 ? '' : '-' . $this->count;
        $file = sprintf('%s/%s%s.xml', $this->directory, $this->label, $suffix);

        file_put_contents($file, (string) $response->getBody());

        $response->getBody()->rewind();

        return $response;
    }

    /**
     * Turns e.g. `SearchTest::testDimensions` into `search-dimensions`.
     */
    public static function labelFor(string $class, string $method): string
    {
        $class = preg_replace('~Test$~', '', substr((string) strrchr('\\' . $class, '\\'), 1));
        $method = preg_replace('~^test~', '', $method);

        $label = (string) preg_replace('~[^A-Za-z0-9]+|(?<=[a-z0-9])(?=[A-Z])~', '-', $class . '-' . $method);

        return strtolower(trim($label, '-'));
    }
}
