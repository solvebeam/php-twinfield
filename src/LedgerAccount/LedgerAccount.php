<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\LedgerAccount;

use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A ledger account of the REST API (Bookkeeping Ledger Accounts): a general ledger account or another
 * dimension, with its GUID.
 *
 * The GUID is the `uid` of the dimension in the XML web services, e.g. of
 * `$twinfield->balanceSheetAccounts()->get('01000')`, so the two can be joined.
 */
final readonly class LedgerAccount
{
    public function __construct(
        public CompanyReference $company,
        /** GUID, e.g. `71106920-53fc-46d6-9e7f-f6d388454455`. */
        public string $id,
        /** Code, e.g. `8000`. */
        public string $code,
        public string $name,
        public LedgerAccountType $type,
        public Subanalysis $subanalysis,
        /** GUID of the default parent account; null when there is none. */
        public ?string $defaultParentAccountId,
    ) {
        Guid::of($id);

        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Ledger account code and name must not be empty.');
        }
    }
}
