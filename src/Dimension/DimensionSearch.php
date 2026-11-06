<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use Closure;
use DateTimeInterface;
use Generator;
use SolveBeam\Twinfield\Finder\FinderQuery;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Twinfield;

/**
 * Immutable search for dimensions of one type, via the Finder (type `DIM`).
 *
 *   $page = $twinfield->activities()->search('A*')
 *       ->inName()
 *       ->modifiedSince(new DateTimeImmutable('2025-01-01'))
 *       ->limit(10)
 *       ->get();
 *
 *   foreach ($twinfield->activities()->search()->all() as $activity) { … }
 *
 * Every method returns a new search, so a base search can be reused.
 *
 * @template T of object
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/finder/
 */
final readonly class DimensionSearch
{
    /**
     * @param Closure(list<string>, int): T $mapRow Maps a Finder row (columns code, name, …) to a summary.
     */
    public function __construct(
        private FinderQuery $query,
        private Closure $mapRow,
    ) {
    }

    /**
     * @template S of object
     *
     * @param string $type Dimension type, e.g. `ACT`.
     * @param Closure(list<string>, int): S $mapRow
     *
     * @return self<S>
     */
    public static function create(Twinfield $twinfield, string $type, Closure $mapRow): self
    {
        return new self($twinfield->finder(FinderType::Dimensions)->option('dimtype', $type), $mapRow);
    }

    /**
     * Search pattern; `*` matches anything, `?` one character. Default `*`.
     *
     * @return self<T>
     */
    public function pattern(string $pattern): self
    {
        return new self($this->query->pattern($pattern), $this->mapRow);
    }

    /**
     * Match the pattern on code and name (default).
     *
     * @return self<T>
     */
    public function inCodeAndName(): self
    {
        return new self($this->query->field(0), $this->mapRow);
    }

    /**
     * Match the pattern on the code only.
     *
     * @return self<T>
     */
    public function inCode(): self
    {
        return new self($this->query->field(1), $this->mapRow);
    }

    /**
     * Match the pattern on the name only.
     *
     * @return self<T>
     */
    public function inName(): self
    {
        return new self($this->query->field(2), $this->mapRow);
    }

    /**
     * Only dimensions changed on or after this moment.
     *
     * @return self<T>
     */
    public function modifiedSince(DateTimeInterface $since): self
    {
        return new self($this->query->option('modifiedsince', $since->format('YmdHis')), $this->mapRow);
    }

    /**
     * Only dimensions in this dimension group; wildcards are allowed.
     *
     * @return self<T>
     */
    public function group(string $group): self
    {
        return new self($this->query->option('group', $group), $this->mapRow);
    }

    /**
     * Results per page (default 100).
     *
     * @return self<T>
     */
    public function limit(int $limit): self
    {
        return new self($this->query->limit($limit), $this->mapRow);
    }

    /**
     * Number of results to skip (default 0).
     *
     * @return self<T>
     */
    public function offset(int $offset): self
    {
        return new self($this->query->offset($offset), $this->mapRow);
    }

    /**
     * One page of results.
     *
     * @return DimensionSearchResult<T>
     */
    public function get(): DimensionSearchResult
    {
        $page = $this->query->get();

        return new DimensionSearchResult(
            $page->totalRows,
            array_map($this->mapRow, $page->rows, array_keys($page->rows)),
        );
    }

    /**
     * All results over all pages, fetched page by page while you iterate.
     *
     * @return Generator<int, T>
     */
    public function all(): Generator
    {
        foreach ($this->query->all() as $index => $row) {
            yield $index => ($this->mapRow)($row, $index);
        }
    }

    /**
     * Number of matching dimensions, with one small request.
     */
    public function count(): int
    {
        return $this->query->limit(1)->get()->totalRows;
    }

    /**
     * The underlying Finder query, e.g. for options that have no method here.
     */
    public function finderQuery(): FinderQuery
    {
        return $this->query;
    }
}
