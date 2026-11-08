<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rate;

use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A rate in search results: code and name. Use get() for the type, unit and prices.
 */
final readonly class RateSummary
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }

    /**
     * From a Finder row; columns: code, name (column names depend on the user's language).
     *
     * @param list<string> $row
     */
    public static function fromFinderRow(array $row, int $index): self
    {
        $code = $row[0] ?? '';
        $name = $row[1] ?? '';

        if ($code === '' || $name === '') {
            throw new UnexpectedResponseException(sprintf(
                'Finder rate row %d has no code or name: %s.',
                $index,
                json_encode($row, JSON_THROW_ON_ERROR),
            ));
        }

        return new self($code, $name);
    }
}
