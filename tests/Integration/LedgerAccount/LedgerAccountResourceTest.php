<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\LedgerAccount;

use SolveBeam\Twinfield\LedgerAccount\LedgerAccountType;
use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live REST API ledger accounts (read-only) of TWINFIELD_COMPANY.
 */
final class LedgerAccountResourceTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireScopes(Scope::ReadBookkeeping);
    }

    public function testSearchesAndReadsAnAccount(): void
    {
        $page = $this->twinfield->ledgerAccounts()->search()->types(LedgerAccountType::Balance)->limit(3)->get();

        self::assertNotEmpty($page->items);
        self::assertGreaterThanOrEqual($page->filteredTotal, $page->total);

        foreach ($page as $account) {
            self::assertSame(LedgerAccountType::Balance, $account->type);
        }

        $account = $this->twinfield->ledgerAccounts()->get($page->items[0]->id);

        self::assertEquals($page->items[0], $account);
    }

    public function testTheGuidIsTheUidOfTheDimension(): void
    {
        $account = $this->twinfield->ledgerAccounts()
            ->search()
            ->types(LedgerAccountType::Balance)
            ->limit(1)
            ->get()
            ->items[0];

        self::assertSame($account->id, strtolower($this->twinfield->balanceSheetAccounts()->get($account->code)->uid));
    }

    public function testReadsAllAccountsOverPages(): void
    {
        $search = $this->twinfield->ledgerAccounts()->search()->types(
            LedgerAccountType::Debtor,
            LedgerAccountType::Creditor,
        );
        $count = 0;

        foreach ($search->limit(100)->all() as $account) {
            self::assertContains($account->type, [LedgerAccountType::Debtor, LedgerAccountType::Creditor]);
            $count++;
        }

        self::assertSame($search->limit(1)->get()->filteredTotal, $count);
    }
}
