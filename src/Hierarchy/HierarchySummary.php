<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A reporting structure in search results: code and name. Use `get()` for the nodes and accounts.
 */
final readonly class HierarchySummary
{
    public function __construct(
        /** Reporting structures belong to the organisation: every company has the same ones. */
        public Organisation $organisation,
        public string $code,
        public string $name,
    ) {
    }

    /**
     * From a Finder row; columns: code, name (column names depend on the user's language).
     *
     * @param list<string> $row
     */
    public static function fromFinderRow(array $row, int $index, Organisation $organisation): self
    {
        $code = $row[0] ?? '';
        $name = $row[1] ?? '';

        if ($code === '' || $name === '') {
            throw new UnexpectedResponseException(sprintf(
                'Finder reporting structure row %d has no code or name: %s.',
                $index,
                json_encode($row, JSON_THROW_ON_ERROR),
            ));
        }

        return new self($organisation, $code, $name);
    }
}
