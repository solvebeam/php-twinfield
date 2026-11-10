<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * An invoice type of the Classic invoicing module, from Finder type `INV`, e.g. `FACTUUR`.
 */
final readonly class InvoiceTypeSummary
{
    public function __construct(
        /** Company the Finder searched in, with its organisation. */
        public CompanyReference $company,
        public string $code,
        public string $name,
    ) {
    }

    /**
     * From a Finder row with the columns code and name.
     *
     * @param list<string> $row
     *
     * @internal
     */
    public static function fromFinderRow(array $row, int $index, CompanyReference $company): self
    {
        $code = $row[0] ?? '';
        $name = $row[1] ?? '';

        if ($code === '' || $name === '' || count($row) !== 2) {
            throw new UnexpectedResponseException(sprintf(
                'Finder invoice type row %d has no code or name: %s.',
                $index,
                json_encode($row, JSON_THROW_ON_ERROR),
            ));
        }

        return new self($company, $code, $name);
    }
}
