<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * One page of Finder results.
 *
 * Column names are in the language of the Twinfield user (e.g. "Naam"),
 * so read values by position rather than by column name.
 *
 * @implements IteratorAggregate<int, list<string>>
 */
final readonly class FinderResult implements IteratorAggregate, Countable
{
    /**
     * @param list<string> $columns
     * @param list<list<string>> $rows
     */
    public function __construct(
        public int $totalRows,
        public array $columns,
        public array $rows,
    ) {
    }

    public static function fromResponse(SearchResponse $response): self
    {
        $data = $response->data;

        return new self(
            totalRows: $data->totalRows ?? 0,
            columns: array_values($data->columns->items ?? []),
            rows: array_values(array_map(
                static fn ($row): array => array_values($row->items),
                $data->items->items ?? [],
            )),
        );
    }

    /**
     * Rows on this page; see $totalRows for the total over all pages.
     */
    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * @return ArrayIterator<int, list<string>>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->rows);
    }
}
