<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A sub-article in search results (Finder `SAR`): code and name. Use `articles()->get()` for the
 * prices and accounts.
 */
final readonly class SubArticleSummary
{
    public function __construct(
        /** Company the Finder searched in, with its organisation. */
        public CompanyReference $company,
        /** Code of the article the sub-article belongs to. */
        public string $articleCode,
        /** Code of the sub-article; null for the only sub-article of an article, which may have no code. */
        public ?string $code,
        public string $name,
    ) {
    }

    /**
     * From a Finder row; columns: code, name (column names depend on the user's language).
     *
     * @param list<string> $row
     *
     * @internal
     */
    public static function fromFinderRow(array $row, int $index, CompanyReference $company, string $articleCode): self
    {
        if (!isset($row[0], $row[1]) || $row[1] === '') {
            throw new UnexpectedResponseException(sprintf(
                'Finder sub-article row %d of article %s has no code column or no name: %s.',
                $index,
                $articleCode,
                json_encode($row, JSON_THROW_ON_ERROR),
            ));
        }

        // An empty code is valid: the only sub-article of an article may have none.
        return new self($company, $articleCode, $row[0] === '' ? null : $row[0], $row[1]);
    }
}
