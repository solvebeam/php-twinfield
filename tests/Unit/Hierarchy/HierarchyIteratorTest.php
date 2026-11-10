<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Hierarchy;

use LogicException;
use PHPUnit\Framework\TestCase;
use RecursiveIteratorIterator;
use RecursiveTreeIterator;
use SolveBeam\Twinfield\Hierarchy\Hierarchy;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccount;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccountBalanceType;
use SolveBeam\Twinfield\Hierarchy\HierarchyNode;
use SolveBeam\Twinfield\Hierarchy\HierarchyNodeAccountIterator;
use SolveBeam\Twinfield\Hierarchy\HierarchyNodeIterator;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use Stringable;

final class HierarchyIteratorTest extends TestCase
{
    public function testDrawsTheNodesAndAccounts(): void
    {
        self::assertSame(
            <<<'TREE'
            \-MGMT Management report
              |-1 Activa
              | |-11 Liquide middelen
              | | |-1100 (BAS, Balance)
              | | |-1110 (BAS, Debit)
              | | \-1110 (BAS, Credit)
              | \-12 Debiteuren
              \-2 Resultaat
                \-8000 (PNL, Balance)
            TREE,
            self::tree(new RecursiveTreeIterator(self::hierarchy())),
        );
    }

    public function testDrawsTheNodesOnly(): void
    {
        self::assertSame(
            <<<'TREE'
            \-MGMT Management report
              |-1 Activa
              | |-11 Liquide middelen
              | \-12 Debiteuren
              \-2 Resultaat
            TREE,
            self::tree(new RecursiveTreeIterator(self::hierarchy()->nodes())),
        );
    }

    public function testDrawsANode(): void
    {
        self::assertSame(
            <<<'TREE'
            |-11 Liquide middelen
            | |-1100 (BAS, Balance)
            | |-1110 (BAS, Debit)
            | \-1110 (BAS, Credit)
            \-12 Debiteuren
            TREE,
            self::tree(new RecursiveTreeIterator(self::hierarchy()->node('1') ?? self::fail())),
        );
    }

    public function testWalksTheNodesDepthFirst(): void
    {
        $codes = [];
        $nodes = new RecursiveIteratorIterator(self::hierarchy()->nodes(), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($nodes as $node) {
            self::assertInstanceOf(HierarchyNode::class, $node);

            $codes[] = $node->code;
        }

        self::assertSame(['MGMT', '1', '11', '12', '2'], $codes);
        self::assertEquals(self::hierarchy()->allNodes(), self::hierarchy()->node('MGMT')?->allNodes());
        self::assertSame(
            $codes,
            array_map(static fn (HierarchyNode $node): string => $node->code, self::hierarchy()->allNodes()),
        );
    }

    public function testLeavesAreAccountsAndEmptyNodes(): void
    {
        $leaves = [];

        foreach (new RecursiveIteratorIterator(self::hierarchy()) as $leaf) {
            self::assertInstanceOf(Stringable::class, $leaf);

            $leaves[] = (string) $leaf;
        }

        self::assertSame(
            ['1100 (BAS, Balance)', '1110 (BAS, Debit)', '1110 (BAS, Credit)', '12 Debiteuren', '8000 (PNL, Balance)'],
            $leaves,
        );
    }

    public function testAnAccountHasNoChildren(): void
    {
        $iterator = new HierarchyNodeAccountIterator([HierarchyAccount::balanceSheet('1100')]);

        self::assertFalse($iterator->hasChildren());

        $this->expectException(LogicException::class);

        $iterator->getChildren();
    }

    public function testAnEmptyIteratorHasNoChildren(): void
    {
        $iterator = new HierarchyNodeIterator([]);

        self::assertFalse($iterator->hasChildren());

        $this->expectException(LogicException::class);

        $iterator->getChildren();
    }

    public function testCollectsAllAccounts(): void
    {
        self::assertSame(
            ['1100', '1110', '1110', '8000'],
            array_map(
                static fn (HierarchyAccount $account): string => $account->code,
                self::hierarchy()->rootNode->allAccounts(),
            ),
        );
    }

    /**
     * @param iterable<string> $lines
     */
    private static function tree(iterable $lines): string
    {
        $tree = '';

        foreach ($lines as $line) {
            $tree .= $line . "\n";
        }

        return rtrim($tree);
    }

    private static function hierarchy(): Hierarchy
    {
        $cash = new HierarchyNode(11, '11', 'Liquide middelen', null, [
            HierarchyAccount::balanceSheet('1100'),
            HierarchyAccount::balanceSheet('1110', HierarchyAccountBalanceType::Debit),
            HierarchyAccount::balanceSheet('1110', HierarchyAccountBalanceType::Credit),
        ], [], 1);
        $receivables = new HierarchyNode(12, '12', 'Debiteuren', null, [], [], 1);
        $assets = new HierarchyNode(1, '1', 'Activa', null, [], [$cash, $receivables], 1);
        $result = new HierarchyNode(2, '2', 'Resultaat', null, [HierarchyAccount::profitAndLoss('8000')], [], 1);
        $root = new HierarchyNode(0, 'MGMT', 'Management report', null, [], [$assets, $result], 1);

        return new Hierarchy(
            new CompanyReference(new Organisation('0b6a5f6e-1111-2222-3333-444455556666', 'EXAMPLE'), '1000'),
            'MGMT',
            'Management report',
            null,
            $root,
            [],
            1,
        );
    }
}
