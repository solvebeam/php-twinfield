<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

use BackedEnum;
use Generator;
use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Immutable search on a list of the REST API, paged with `offset` and `limit`. Resources create the
 * subclasses, which add the filters of their endpoint:
 *
 *   $page = $twinfield->ledgerAccounts()->search('80*')->limit(100)->get();
 *
 *   foreach ($twinfield->ledgerAccounts()->search()->all() as $account) { … }
 *
 * The REST API answers with `{"items": […], "total": …, "filteredTotal": …}`; without a limit it gives
 * 50 items.
 *
 * @template T of object
 */
abstract readonly class RestSearch
{
    /** Page size of all(): Twinfield accepts thousands, 500 keeps responses small. */
    private const PAGE_SIZE = 500;

    /**
     * @param array<string, scalar|BackedEnum|list<scalar|BackedEnum>|null> $parameters
     */
    final public function __construct(
        protected Twinfield $twinfield,
        protected string $path,
        protected array $parameters = [],
    ) {
    }

    /**
     * Skip the first matches, e.g. `offset(100)` for the third page of 50.
     *
     * @return static
     */
    public function offset(int $offset): static
    {
        if ($offset < 0) {
            throw new InvalidArgumentException(sprintf('Offset must be 0 or more, %d given.', $offset));
        }

        return $this->with('offset', $offset);
    }

    /**
     * Items per page; Twinfield gives 50 without a limit.
     *
     * @return static
     */
    public function limit(int $limit): static
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(sprintf('Limit must be 1 or more, %d given.', $limit));
        }

        return $this->with('limit', $limit);
    }

    /**
     * One page.
     *
     * @return RestPage<T>
     */
    public function get(): RestPage
    {
        $json = $this->twinfield->rest('GET', $this->path, $this->parameters)->json();
        $company = $this->twinfield->companyReference();
        $items = array_map(fn (JsonValue $item): object => $this->map($item, $company), $json->list('items'));
        $offset = $this->parameters['offset'] ?? 0;

        return new RestPage(
            $items,
            $json->int('total'),
            $json->int('filteredTotal'),
            is_int($offset) ? $offset : 0,
        );
    }

    /**
     * All matches over all pages, from the offset; fetches the next page only when you get there.
     *
     * @return Generator<int, T>
     */
    public function all(): Generator
    {
        $limit = $this->parameters['limit'] ?? self::PAGE_SIZE;
        $search = $this->limit(is_int($limit) ? $limit : self::PAGE_SIZE);

        do {
            $page = $search->get();

            yield from $page->items;

            $search = $search->offset($page->offset + count($page->items));
        } while ($page->hasMore() && $page->items !== []);
    }

    /**
     * Maps one item of the list.
     *
     * @return T
     *
     * @throws UnexpectedResponseException
     */
    abstract protected function map(JsonValue $item, CompanyReference $company): object;

    /**
     * The search with one more query parameter; null removes it.
     *
     * @param scalar|BackedEnum|list<scalar|BackedEnum>|null $value
     *
     * @return static
     */
    protected function with(string $name, mixed $value): static
    {
        $parameters = $this->parameters;
        $parameters[$name] = $value;
        $parameters = array_filter($parameters, static fn (mixed $value): bool => $value !== null);

        // Same subclass, so the same item type; PHPStan can't see that through `new static`.
        // @phpstan-ignore return.type
        return new static($this->twinfield, $this->path, $parameters);
    }

    /**
     * Sort by a field of the endpoint, ascending (`asc=true`) or descending.
     *
     * @return static
     */
    protected function sort(string $field, bool $ascending): static
    {
        return $this->with('orderBy', $field)->with('asc', $ascending);
    }
}
