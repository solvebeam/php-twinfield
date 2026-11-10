<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\LedgerAccount;

use DateTimeInterface;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * Search on the ledger accounts, created by `$twinfield->ledgerAccounts()->search()`.
 *
 * @extends RestSearch<LedgerAccount>
 */
final readonly class LedgerAccountSearch extends RestSearch
{
    /**
     * Search pattern on code and name, e.g. `80*`; `*` matches anything.
     */
    public function pattern(string $pattern): self
    {
        return $this->with('searchPattern', $pattern);
    }

    /**
     * Only accounts of these types, e.g. `types(LedgerAccountType::Balance, LedgerAccountType::ProfitAndLoss)`.
     */
    public function types(LedgerAccountType ...$types): self
    {
        $values = array_map(static fn (LedgerAccountType $type): string => $type->value, $types);

        return $this->with('types', $values === [] ? null : implode(',', $values));
    }

    /**
     * Only accounts changed on or after this moment.
     */
    public function modifiedSince(DateTimeInterface $since): self
    {
        return $this->with('modifiedSince', $since->format(DATE_ATOM));
    }

    public function orderByCode(bool $ascending = true): self
    {
        return $this->sort('code', $ascending);
    }

    public function orderByName(bool $ascending = true): self
    {
        return $this->sort('name', $ascending);
    }

    protected function map(JsonValue $item, CompanyReference $company): LedgerAccount
    {
        return LedgerAccountReader::read($item, $company);
    }
}
