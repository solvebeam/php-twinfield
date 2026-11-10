<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A node of a reporting structure in search results: code and name, without the node ID. Use
 * `get()` and `node()` for the ID, the accounts and the child nodes.
 */
final readonly class HierarchyNodeSummary
{
    public function __construct(
        public Organisation $organisation,
        /** Code of the reporting structure the node belongs to. */
        public string $hierarchyCode,
        public string $code,
        public string $name,
    ) {
    }

    /**
     * From a Finder row; columns: code, name.
     *
     * @param list<string> $row
     *
     * @internal
     */
    public static function fromFinderRow(
        array $row,
        int $index,
        Organisation $organisation,
        string $hierarchyCode,
    ): self {
        $code = $row[0] ?? '';
        $name = $row[1] ?? '';

        if ($code === '' || $name === '') {
            throw new UnexpectedResponseException(sprintf(
                'Finder reporting structure node row %d has no code or name: %s.',
                $index,
                json_encode($row, JSON_THROW_ON_ERROR),
            ));
        }

        return new self($organisation, $hierarchyCode, $code, $name);
    }
}
