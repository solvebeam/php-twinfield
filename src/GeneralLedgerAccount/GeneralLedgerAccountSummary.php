<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\GeneralLedgerAccount;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A general ledger account in search results: code and name. Use get() for all fields.
 */
final readonly class GeneralLedgerAccountSummary
{
    public function __construct(
        /** Company the Finder searched in, with its organisation. */
        public CompanyReference $company,
        public string $code,
        public string $name,
        /** True for a hidden account; only in searches with includeHidden(). */
        public bool $hidden = false,
    ) {
    }

    /**
     * From a Finder row; columns: code, name, and with includeHidden() the status `active` or
     * `hidden` (column names depend on the user's language, the status does not).
     *
     * @param list<string> $row
     */
    public static function fromFinderRow(array $row, int $index, CompanyReference $company): self
    {
        $code = $row[0] ?? '';
        $name = $row[1] ?? '';

        if ($code === '' || $name === '') {
            throw new UnexpectedResponseException(sprintf(
                'Finder general ledger account row %d has no code or name: %s.',
                $index,
                json_encode($row, JSON_THROW_ON_ERROR),
            ));
        }

        // Without includeHidden() Twinfield gives only active accounts and no status column.
        $hidden = match ($row[2] ?? 'active') {
            'active' => false,
            'hidden' => true,
            default => throw new UnexpectedResponseException(sprintf(
                'Finder general ledger account row %d has status "%s", expected active or hidden.',
                $index,
                $row[2],
            )),
        };

        return new self($company, $code, $name, $hidden);
    }
}
