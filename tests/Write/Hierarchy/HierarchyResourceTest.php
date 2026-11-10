<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Hierarchy;

use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountSummary;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessType;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccount;
use SolveBeam\Twinfield\Hierarchy\HierarchyException;
use SolveBeam\Twinfield\Hierarchy\HierarchyInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyNodeInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyNotFoundException;
use SolveBeam\Twinfield\Hierarchy\HierarchySaveMessage;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates a temporary reporting structure SDKTEST9xx, changes it, binds and unbinds accounts of
 * the write company, and always deletes it again.
 *
 * Reporting structures belong to the organisation: while the test runs, every company of the
 * organisation sees SDKTEST9xx. Record the responses (`TWINFIELD_RECORD=1`) to replace the
 * fictitious fixtures of Save, Bind, Unbind and Delete.
 */
final class HierarchyResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $hierarchies = $this->twinfield->hierarchies();
        [$first, $second] = $this->profitAndLossAccounts();
        $code = $this->unusedCode();

        try {
            $created = $hierarchies->save(
                HierarchyInput::create($code, 'SDK test')
                    ->addNode(HierarchyNodeInput::create('R', 'Revenue')->accounts($first))
                    ->addNode(HierarchyNodeInput::create('C', 'Costs'))
                    ->addNode(HierarchyNodeInput::create('C1', 'Staff'), parent: 'C')
                    ->accessRights(
                        HierarchyAccessInput::user($this->twinfield->userCode(), HierarchyAccessType::Edit),
                        HierarchyAccessInput::everyone(HierarchyAccessType::None),
                    ),
            );

            self::assertSame($code, $created->code);
            self::assertSame($code, $created->rootNode->code);
            self::assertEquals([$first], $created->node('R')?->accounts);
            self::assertGreaterThan(0, $created->node('C1')?->id, 'New nodes get an ID.');
            self::assertEquals($created, $hierarchies->get($code));

            $changed = $hierarchies->save(
                $created->edit()
                    ->description('Changed by the SDK write test')
                    ->changeNode(
                        'C1',
                        static fn (HierarchyNodeInput $node): HierarchyNodeInput => $node->name('Personnel'),
                    ),
            );
            $staff = $changed->node('C1') ?? self::fail('Node C1 is gone.');

            self::assertSame('Changed by the SDK write test', $changed->description);
            self::assertSame('Personnel', $staff->name);
            self::assertSame($created->node('C1')?->id, $staff->id, 'The node keeps its ID.');
            self::assertGreaterThan($created->touched, $changed->touched);

            $this->assertRefusesAStaleSave($created->edit()->name('Stale'));

            $hierarchies->bind($staff, $second);
            self::assertEquals([$second], $hierarchies->get($code)->node('C1')?->accounts);

            $hierarchies->unbind($staff, $second);
            self::assertSame([], $hierarchies->get($code)->node('C1')?->accounts);
        } finally {
            try {
                $hierarchies->delete($code);
            } catch (HierarchyNotFoundException) {
                // Not created.
            }
        }

        $this->expectException(HierarchyNotFoundException::class);

        $hierarchies->get($code);
    }

    private function assertRefusesAStaleSave(HierarchyInput $stale): void
    {
        try {
            $this->twinfield->hierarchies()->save($stale);
            self::fail('Expected Touched for a reporting structure saved by someone else in the meantime.');
        } catch (HierarchyException $exception) {
            self::assertSame(HierarchySaveMessage::Touched, $exception->messages[0]->code);
        }
    }

    /**
     * Two profit and loss accounts of the write company.
     *
     * @return array{HierarchyAccount, HierarchyAccount}
     */
    private function profitAndLossAccounts(): array
    {
        $accounts = $this->twinfield->profitAndLossAccounts()->search()->limit(2)->get();

        if (count($accounts->items) < 2) {
            self::markTestSkipped(sprintf('Company %s has fewer than two profit and loss accounts.', $this->company()));
        }

        return array_map(
            static fn (GeneralLedgerAccountSummary $account): HierarchyAccount
                => HierarchyAccount::profitAndLoss($account->code),
            [$accounts->items[0], $accounts->items[1]],
        );
    }

    private function unusedCode(): string
    {
        $codes = [];

        foreach ($this->twinfield->hierarchies()->search('SDKTEST*')->all() as $summary) {
            $codes[] = $summary->code;
        }

        for ($number = 900; $number <= 999; $number++) {
            if (!in_array('SDKTEST' . $number, $codes, true)) {
                return 'SDKTEST' . $number;
            }
        }

        self::fail('No unused SDKTEST9xx code.');
    }
}
