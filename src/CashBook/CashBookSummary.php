<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashBook;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A cash book in search results: code and name. Use get() for the short name and ledger account.
 */
final readonly class CashBookSummary
{
    public function __construct(
        /** Company the Finder searched in, with its organisation. */
        public CompanyReference $company,
        public string $code,
        public string $name,
    ) {
    }

    /**
     * From a Finder row (type BNK, banktype 1); columns: code, name.
     *
     * @param list<string> $row
     *
     * @internal
     */
    public static function fromFinderRow(array $row, int $index, CompanyReference $company): self
    {
        $code = $row[0] ?? '';
        $name = $row[1] ?? '';

        if ($code === '' || $name === '') {
            throw new UnexpectedResponseException(sprintf(
                'Finder cash book row %d has no code or name: %s.',
                $index,
                json_encode($row, JSON_THROW_ON_ERROR),
            ));
        }

        return new self($company, $code, $name);
    }
}
