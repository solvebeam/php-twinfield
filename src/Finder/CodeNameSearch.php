<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

use Closure;
use DateTimeInterface;
use Generator;
use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\Rate\RateType;
use SolveBeam\Twinfield\VatCode\VatCodeType;

/**
 * Immutable Finder search on code and name, for Finder types with the columns code and name
 * (dimensions, bank books, …). Resources create it, e.g. activities and bank books:
 *
 *   $page = $twinfield->activities()->search('A*')
 *       ->inName()
 *       ->modifiedSince(new DateTimeImmutable('2025-01-01'))
 *       ->limit(10)
 *       ->get();
 *
 *   foreach ($twinfield->bankBooks()->search()->all() as $bankBook) { … }
 *
 * Every method returns a new search, so a base search can be reused.
 *
 * @template T of object
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/finder/
 */
final readonly class CodeNameSearch
{
    /**
     * @param FinderQuery $query Finder query with the type and its fixed options, e.g. `dimtype`.
     * @param Closure(FinderQuery): (Closure(list<string>, int): T) $mapper Gives the row mapper
     *                                                                     for a query, with the
     *                                                                     context it needs.
     */
    private function __construct(
        private FinderQuery $query,
        private Closure $mapper,
    ) {
    }

    /**
     * Search for items of a company, e.g. dimensions or VAT codes. The summaries get the company
     * the Finder searches in: the `company` option, or else the company of the client.
     *
     * @template TSummary of object
     *
     * @param FinderQuery $query Finder query with the type and its fixed options, e.g. `dimtype`.
     * @param Closure(list<string>, int, CompanyReference): TSummary $mapRow Maps a Finder row
     *                                                                       (columns code, name,
     *                                                                       …) to a summary.
     *
     * @return self<TSummary>
     */
    public static function forCompany(FinderQuery $query, Closure $mapRow): self
    {
        return new self($query, static function (FinderQuery $query) use ($mapRow): Closure {
            $company = $query->companyReference();

            return static fn (array $row, int $index): object => $mapRow($row, $index, $company);
        });
    }

    /**
     * Search for items of the organisation: offices and users. The summaries get the
     * organisation of the access token.
     *
     * @template TSummary of object
     *
     * @param Closure(list<string>, int, Organisation): TSummary $mapRow
     *
     * @return self<TSummary>
     */
    public static function forOrganisation(FinderQuery $query, Closure $mapRow): self
    {
        return new self($query, static function (FinderQuery $query) use ($mapRow): Closure {
            $organisation = $query->organisation();

            return static fn (array $row, int $index): object => $mapRow($row, $index, $organisation);
        });
    }

    /**
     * Search pattern; `*` matches anything, `?` one character. Default `*`.
     *
     * @return self<T>
     */
    public function pattern(string $pattern): self
    {
        return new self($this->query->pattern($pattern), $this->mapper);
    }

    /**
     * Match the pattern on code and name (default).
     *
     * @return self<T>
     */
    public function inCodeAndName(): self
    {
        return new self($this->query->field(0), $this->mapper);
    }

    /**
     * Match the pattern on the code only.
     *
     * @return self<T>
     */
    public function inCode(): self
    {
        return new self($this->query->field(1), $this->mapper);
    }

    /**
     * Match the pattern on the name only.
     *
     * @return self<T>
     */
    public function inName(): self
    {
        return new self($this->query->field(2), $this->mapper);
    }

    /**
     * Match the pattern on the bank account number. Customers and suppliers only; the rows
     * then have the bank account as third column.
     *
     * @return self<T>
     */
    public function inBankAccount(): self
    {
        return new self($this->query->field(3), $this->mapper);
    }

    /**
     * Match the pattern on the address fields, e.g. `search('*Amsterdam*')->inAddress()`.
     * Customers and suppliers only.
     *
     * @return self<T>
     */
    public function inAddress(): self
    {
        return new self($this->query->field(4), $this->mapper);
    }

    /**
     * Only items changed on or after this moment.
     *
     * @return self<T>
     */
    public function modifiedSince(DateTimeInterface $since): self
    {
        return new self($this->query->option('modifiedsince', $since->format('YmdHis')), $this->mapper);
    }

    /**
     * Only dimensions in this dimension group; wildcards are allowed. Dimension searches only.
     *
     * @return self<T>
     */
    public function group(string $group): self
    {
        return $this->option('group', $group);
    }

    /**
     * Also hidden items; the rows then have the status (`active` or `hidden`) as third column.
     * Dimension searches only.
     *
     * @return self<T>
     */
    public function includeHidden(): self
    {
        return $this->option('includehidden', '1');
    }

    /**
     * Only dimension groups with at least one dimension of this type, e.g. `DEB`. Dimension
     * group searches only.
     *
     * @return self<T>
     */
    public function dimensionType(string $type): self
    {
        return $this->option('dimtype', $type);
    }

    /**
     * Only dimension types of this financial level, 1–6: e.g. 1 for BAS and PNL, 2 for DEB,
     * CRD and KPL. Dimension type searches only.
     *
     * @return self<T>
     */
    public function financialLevel(int $level): self
    {
        if ($level < 1 || $level > 6) {
            throw new InvalidArgumentException(sprintf('Financial level must be 1 to 6, %d given.', $level));
        }

        return $this->option('finlevel', (string) $level);
    }

    /**
     * Only the dimension types that have a dimension with this code, e.g. `1000` gives BAS
     * and DEB when both have a dimension 1000. Dimension type searches only.
     *
     * @return self<T>
     */
    public function ofDimension(string $code): self
    {
        return $this->option('dim', $code);
    }

    /**
     * Only offices that are not in a fiscal unity (tax group), are no template and have no VAT
     * declaration in progress. Office searches only.
     *
     * @return self<T>
     */
    public function withoutTaxGroup(): self
    {
        return $this->option('withouttaxgroup', '1');
    }

    /**
     * Also users that the access rules hide, e.g. users without access to the companies of the
     * logged in user (Finder option `accessrules=0`). Only for users with access to user
     * management. User searches only.
     *
     * @return self<T>
     */
    public function withoutAccessRules(): self
    {
        return $this->option('accessrules', '0');
    }

    /**
     * Users with access to at least one of the companies of the logged in user (Finder option
     * `mutualoffices=1`). This can give more users than the default search, which leaves out
     * some users of the organisation. User searches only.
     *
     * @return self<T>
     */
    public function withMutualCompanies(): self
    {
        return $this->option('mutualoffices', '1');
    }

    /**
     * Only rates of this type: hours or quantities, e.g. `rateType(RateType::Quantity)`. Rate
     * searches only.
     *
     * @return self<T>
     */
    public function rateType(RateType $type): self
    {
        return $this->option('ratetype', $type->value);
    }

    /**
     * Only VAT codes for sales or for purchases, e.g. `vatType(VatCodeType::Sales)`. VAT code
     * searches only.
     *
     * @return self<T>
     */
    public function vatType(VatCodeType $type): self
    {
        return $this->option('vattype', $type->value);
    }

    /**
     * Any other Finder option of the type, e.g. `option('group', 'GRP1')` for dimensions in a group.
     *
     * @return self<T>
     */
    public function option(string $name, string $value): self
    {
        return new self($this->query->option($name, $value), $this->mapper);
    }

    /**
     * Results per page (default 100).
     *
     * @return self<T>
     */
    public function limit(int $limit): self
    {
        return new self($this->query->limit($limit), $this->mapper);
    }

    /**
     * Number of results to skip (default 0).
     *
     * @return self<T>
     */
    public function offset(int $offset): self
    {
        return new self($this->query->offset($offset), $this->mapper);
    }

    /**
     * One page of results.
     *
     * @return CodeNameSearchResult<T>
     */
    public function get(): CodeNameSearchResult
    {
        // The context first: a search without company fails before the request.
        $mapRow = ($this->mapper)($this->query);
        $page = $this->query->get();

        return new CodeNameSearchResult(
            $page->totalRows,
            array_map($mapRow, $page->rows, array_keys($page->rows)),
        );
    }

    /**
     * All results over all pages, fetched page by page while you iterate.
     *
     * @return Generator<int, T>
     */
    public function all(): Generator
    {
        $mapRow = ($this->mapper)($this->query);

        foreach ($this->query->all() as $index => $row) {
            yield $index => $mapRow($row, $index);
        }
    }

    /**
     * Number of matching items, with one small request.
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
