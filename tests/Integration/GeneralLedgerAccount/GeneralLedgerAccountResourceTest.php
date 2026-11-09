<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\GeneralLedgerAccount;

use PHPUnit\Framework\Attributes\DataProvider;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountResource;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountSummary;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountType;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live general ledger account searches and reads (read-only) against TWINFIELD_COMPANY, for
 * balance sheet and profit and loss accounts. Every company has both.
 */
final class GeneralLedgerAccountResourceTest extends IntegrationTestCase
{
    /**
     * @return iterable<string, array{GeneralLedgerAccountType}>
     */
    public static function types(): iterable
    {
        yield 'balance sheet' => [GeneralLedgerAccountType::BalanceSheet];
        yield 'profit and loss' => [GeneralLedgerAccountType::ProfitAndLoss];
    }

    #[DataProvider('types')]
    public function testSearchesAndReadsAccounts(GeneralLedgerAccountType $type): void
    {
        $accounts = $this->accounts($type);
        $page = $accounts->search()->limit(5)->get();

        self::assertGreaterThan(0, $page->totalRows);
        self::assertSame($page->totalRows, $accounts->search()->count());

        $summary = $page->items[0];
        $account = $accounts->get($summary->code);

        self::assertSame($type, $account->type);
        self::assertSame($summary->code, $account->code);
        self::assertSame($summary->name, $account->name);
        self::assertSame($this->environment->config->company(), $account->company->code);
        self::assertNotSame('', $account->uid);
        self::assertNotNull($account->modified, 'A read response has <modified>.');
    }

    #[DataProvider('types')]
    public function testReadsTenAccountsAndEditsThem(GeneralLedgerAccountType $type): void
    {
        $accounts = $this->accounts($type);
        $count = 0;

        foreach ($accounts->search()->limit(50)->all() as $summary) {
            $account = $accounts->get($summary->code);
            $input = $account->edit();

            self::assertSame($type, $input->type);
            self::assertSame($account->subAnalysis->value, $input->value('financials/subanalyse'));
            self::assertSame(count($account->childValidations), count($input->childValidations ?? []));

            if (++$count === 10) {
                break;
            }
        }

        self::assertSame(10, $count);
    }

    #[DataProvider('types')]
    public function testSearchesIncludingHidden(GeneralLedgerAccountType $type): void
    {
        $accounts = $this->accounts($type);

        $all = $accounts->search()->includeHidden()->count();
        $active = $accounts->search()->count();

        self::assertGreaterThanOrEqual($active, $all);
        // Reading the rows checks the status column (active or hidden).
        self::assertCount(min($all, 20), $accounts->search()->includeHidden()->limit(20)->get()->items);
    }

    public function testSearchesByCode(): void
    {
        $accounts = $this->twinfield->profitAndLossAccounts();
        $first = $accounts->search()->limit(1)->get()->items[0] ?? self::fail('Expected a profit and loss account.');

        $codes = array_map(
            static fn (GeneralLedgerAccountSummary $account): string => $account->code,
            iterator_to_array($accounts->search($first->code)->inCode()->all(), false),
        );

        self::assertSame([$first->code], $codes);
    }

    public function testAProfitAndLossAccountIsNoBalanceSheetAccount(): void
    {
        $first = $this->twinfield->profitAndLossAccounts()->search()->limit(1)->get()->items[0]
            ?? self::fail('Expected a profit and loss account.');

        self::assertNull($this->twinfield->balanceSheetAccounts()->find($first->code));
    }

    public function testUnknownAccountIsNotFound(): void
    {
        $accounts = $this->twinfield->balanceSheetAccounts();

        self::assertNull($accounts->find('ZZZZ999'));

        $this->expectException(DimensionNotFoundException::class);

        $accounts->get('ZZZZ999');
    }

    private function accounts(GeneralLedgerAccountType $type): GeneralLedgerAccountResource
    {
        return match ($type) {
            GeneralLedgerAccountType::BalanceSheet => $this->twinfield->balanceSheetAccounts(),
            GeneralLedgerAccountType::ProfitAndLoss => $this->twinfield->profitAndLossAccounts(),
        };
    }
}
