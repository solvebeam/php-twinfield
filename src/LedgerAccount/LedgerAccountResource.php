<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\LedgerAccount;

use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Ledger accounts of the company on the REST API (Bookkeeping Ledger Accounts): general ledger
 * accounts and the other dimensions, with their GUIDs. Read-only.
 *
 *   $accounts = $twinfield->company('1000')->ledgerAccounts();
 *
 *   foreach ($accounts->search()->types(LedgerAccountType::ProfitAndLoss)->all() as $account) {
 *       echo $account->code, ' ', $account->name, PHP_EOL;
 *   }
 *
 *   $account = $accounts->get('71106920-53fc-46d6-9e7f-f6d388454455');
 *
 * Use it to turn the GUIDs of the REST API (e.g. the account of a VAT code or a budget line) into
 * codes. Needs one of the scopes `readBookkeeping`, `readBudgets`, `readCustomers`, `readFixedAssets`,
 * `readSuppliers`, `readVat` or `readVatGroup`. For everything else about general ledger accounts use
 * `balanceSheetAccounts()` and `profitAndLossAccounts()`.
 *
 * @see https://api.accounting.twinfield.com/Api/swagger/ui/index#!/Bookkeeping32Ledger32Accounts
 */
final readonly class LedgerAccountResource
{
    private const PATH = '/organisations/{organisationId}/companies/{companyId}/bookkeeping/accounts';

    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    public function search(string $pattern = '*'): LedgerAccountSearch
    {
        $search = new LedgerAccountSearch($this->twinfield, self::PATH);

        return $pattern === '*' ? $search : $search->pattern($pattern);
    }

    /**
     * @param string $id GUID of the account.
     *
     * @throws NotFoundException When the company has no account with this GUID.
     */
    public function get(string $id): LedgerAccount
    {
        $json = $this->twinfield->rest('GET', self::PATH . '/' . Guid::of($id))->json();

        return LedgerAccountReader::read($json, $this->twinfield->companyReference());
    }
}
