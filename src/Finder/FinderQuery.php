<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

use Generator;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\Data\ArrayOfArrayOfString;
use SolveBeam\Twinfield\Data\ArrayOfString;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\Twinfield;

/**
 * Immutable query builder for the Twinfield Finder web service.
 *
 *   $result = $twinfield->finder(FinderType::Dimensions)
 *       ->pattern('13*')
 *       ->option('dimtype', 'DEB')
 *       ->limit(10)
 *       ->get();
 *
 * Every method returns a new query, so a base query can be reused.
 */
final readonly class FinderQuery
{
    /**
     * @param array<string, string> $options
     */
    public function __construct(
        private Twinfield $twinfield,
        private FinderType $type,
        private string $pattern = '*',
        private int $field = 0,
        private int $offset = 0,
        private int $limit = 100,
        private array $options = [],
    ) {
    }

    /**
     * Search pattern; `*` matches anything, `?` one character.
     */
    public function pattern(string $pattern): self
    {
        return $this->with(pattern: $pattern);
    }

    /**
     * Field to search in. 0 = code and name (default); the meaning of other values depends on the type.
     */
    public function field(int $field): self
    {
        return $this->with(field: $field);
    }

    /**
     * Rows per page (default 100).
     */
    public function limit(int $limit): self
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be at least 1.');
        }

        return $this->with(limit: $limit);
    }

    /**
     * Number of rows to skip (default 0).
     */
    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be 0 or more.');
        }

        return $this->with(offset: $offset);
    }

    /**
     * Extra Finder option, e.g. `option('dimtype', 'DEB')`. Setting a name again replaces it.
     */
    public function option(string $name, string $value): self
    {
        return $this->with(options: [...$this->options, $name => $value]);
    }

    /**
     * One page of results.
     */
    public function get(): FinderResult
    {
        $response = $this->twinfield->call('finder', $this->toSearch())->mapTo(SearchResponse::class);

        return FinderResult::fromResponse($response);
    }

    /**
     * All rows over all pages, fetched page by page while you iterate.
     *
     * @return Generator<int, list<string>>
     */
    public function all(): Generator
    {
        $query = $this;
        $index = 0;

        do {
            $page = $query->get();

            foreach ($page->rows as $row) {
                yield $index++ => $row;
            }

            // Advance by the rows received, so a short page never skips rows.
            $query = $query->offset($query->offset + count($page->rows));
        } while ($page->rows !== [] && $query->offset < $page->totalRows);
    }

    /**
     * The company the Finder searches in: the `company` option (or the deprecated `office`
     * option) when set, otherwise the company of the client.
     *
     * @throws LogicException When there is neither.
     */
    public function companyReference(): CompanyReference
    {
        $code = $this->options['company'] ?? $this->options['office'] ?? $this->twinfield->companyCode();

        if ($code === null) {
            throw new LogicException(sprintf(
                'Finder search %s needs a company: use company() first, or the company option.',
                $this->type->value,
            ));
        }

        return new CompanyReference($this->twinfield->organisation(), $code);
    }

    /**
     * The organisation of the access token.
     */
    public function organisation(): Organisation
    {
        return $this->twinfield->organisation();
    }

    public function toSearch(): Search
    {
        $options = array_map(
            static fn (string $name, string $value): ArrayOfString => new ArrayOfString([$name, $value]),
            array_keys($this->options),
            array_values($this->options),
        );

        return new Search(
            type: $this->type->value,
            pattern: $this->pattern,
            field: $this->field,
            firstRow: $this->offset + 1,
            maxRows: $this->limit,
            options: $options === [] ? null : new ArrayOfArrayOfString($options),
        );
    }

    /**
     * @param array<string, string>|null $options
     */
    private function with(
        ?string $pattern = null,
        ?int $field = null,
        ?int $offset = null,
        ?int $limit = null,
        ?array $options = null,
    ): self {
        return new self(
            $this->twinfield,
            $this->type,
            $pattern ?? $this->pattern,
            $field ?? $this->field,
            $offset ?? $this->offset,
            $limit ?? $this->limit,
            $options ?? $this->options,
        );
    }
}
