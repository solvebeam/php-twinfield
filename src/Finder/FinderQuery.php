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
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Soap\SoapResponse;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

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
     * Search in this company (Finder option `company`) instead of the company of the client.
     */
    public function company(string $code): self
    {
        if ($code === '') {
            throw new InvalidArgumentException('Company code must not be empty.');
        }

        return $this->option('company', $code);
    }

    /**
     * Search in all companies the user has access to (Finder option `company=*`). The rows then
     * end with two extra columns: the company code and the company ID. Twinfield documents this
     * for `BNK`, `CUR`, `DIM`, `PAY`, `TRS`, `VAT`, `VATN` and `VGM`; live, `DMT` supports it too.
     * Other types ignore it and search in the company of the client.
     */
    public function allCompanies(): self
    {
        return $this->option('company', '*');
    }

    /**
     * True when the query searches in all companies (`company=*`).
     */
    public function isAllCompanies(): bool
    {
        return ($this->options['company'] ?? $this->options['office'] ?? null) === '*';
    }

    /**
     * Extra Finder option, e.g. `option('dimtype', 'DEB')`. Setting a name again replaces it.
     *
     * @throws InvalidArgumentException For `company` together with the deprecated `office`;
     *                                  Twinfield refuses them together.
     */
    public function option(string $name, string $value): self
    {
        $other = match ($name) {
            'company' => 'office',
            'office' => 'company',
            default => null,
        };

        if ($other !== null && array_key_exists($other, $this->options)) {
            throw new InvalidArgumentException(sprintf(
                'Finder options company and office cannot be used together; %s is already set.',
                $other,
            ));
        }

        return $this->with(options: [...$this->options, $name => $value]);
    }

    public function type(): FinderType
    {
        return $this->type;
    }

    /**
     * One page of results.
     *
     * @throws FinderException When Twinfield refuses the search, e.g. an option the type does not support.
     */
    public function get(): FinderResult
    {
        $response = $this->twinfield->call('finder', $this->toSearch());
        $errors = array_values(array_filter(
            self::messages($response),
            static fn (FinderMessage $message): bool => $message->type === FinderMessageType::Error,
        ));

        if ($errors !== []) {
            throw new FinderException($this->type, $errors);
        }

        return FinderResult::fromResponse($response->mapTo(SearchResponse::class));
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
     * @throws LogicException When there is neither, or when the query searches in all companies.
     */
    public function companyReference(): CompanyReference
    {
        if ($this->isAllCompanies()) {
            throw new LogicException(sprintf(
                'Finder search %s is in all companies (company=*): each row has its own company.',
                $this->type->value,
            ));
        }

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
     * Messages in `<SearchResult>`: empty when the search succeeds.
     *
     * @return list<FinderMessage>
     */
    private static function messages(SoapResponse $response): array
    {
        $element = $response->payloadElement();

        if ($element === null || $element->localName !== 'SearchResponse') {
            throw new UnexpectedResponseException(sprintf(
                'Expected <SearchResponse>, got %s.',
                $element === null ? 'an empty SOAP body' : '<' . $element->localName . '>',
            ));
        }

        // An empty <SearchResult/> when there are no messages; left out is fine too.
        $result = (new XmlElement($element, 'SearchResponse'))->nullableChild('SearchResult');

        return array_map(
            static fn (XmlElement $message): FinderMessage => new FinderMessage(
                type: $message->enum('Type', FinderMessageType::class),
                code: $message->enum('Code', FinderErrorCode::class),
                text: $message->string('Text'),
                parameters: array_map(
                    static fn (XmlElement $parameter): string => $parameter->text(),
                    $message->child('Parameters')->list('string'),
                ),
            ),
            $result?->list('MessageOfErrorCodes') ?? [],
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
