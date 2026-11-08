<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A fixed asset in search results: code and name. Use get() for all fields.
 */
final readonly class FixedAssetSummary
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
                'Finder fixed asset row %d has no code or name: %s.',
                $index,
                json_encode($row, JSON_THROW_ON_ERROR),
            ));
        }

        return new self($code, $name);
    }
}
