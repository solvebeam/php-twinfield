<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Hierarchy;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessType;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccount;
use SolveBeam\Twinfield\Hierarchy\HierarchyInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyNode;
use SolveBeam\Twinfield\Hierarchy\HierarchyNodeInput;

final class HierarchyInputTest extends TestCase
{
    public function testANewStructureHasARootNodeWithItsCodeAndName(): void
    {
        $input = HierarchyInput::create('MGMT', 'Management report');

        self::assertSame('MGMT', $input->rootNode->code);
        self::assertSame('Management report', $input->rootNode->name);
        self::assertNull($input->rootNode->id);
        self::assertSame(0, $input->touched);
        self::assertSame([], $input->accessRights);
    }

    public function testAddsNodesUnderAParent(): void
    {
        $input = HierarchyInput::create('MGMT', 'Management report')
            ->addNode(HierarchyNodeInput::create('C', 'Costs'))
            ->addNode(HierarchyNodeInput::create('C1', 'Staff'), parent: 'C')
            ->addNode(HierarchyNodeInput::create('C2', 'Housing'), parent: 'C');

        self::assertSame(['C1', 'C2'], self::codes($input->rootNode->children[0]->children));
    }

    public function testChangesAndRemovesNodes(): void
    {
        $input = HierarchyInput::create('MGMT', 'Management report')
            ->addNode(HierarchyNodeInput::create('C', 'Costs')->addChild(
                HierarchyNodeInput::create('C1', 'Staff'),
                HierarchyNodeInput::create('C2', 'Housing'),
            ))
            ->changeNode(
                'C2',
                static fn (HierarchyNodeInput $node): HierarchyNodeInput
                    => $node->accounts(HierarchyAccount::profitAndLoss('4300')),
            )
            ->removeNode('C1');

        $costs = $input->rootNode->children[0];

        self::assertSame(['C2'], self::codes($costs->children));
        self::assertEquals([HierarchyAccount::profitAndLoss('4300')], $costs->children[0]->accounts);
    }

    public function testRefusesAnUnknownNode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no node X below node MGMT.');

        HierarchyInput::create('MGMT', 'Management report')->removeNode('X');
    }

    public function testRefusesToRemoveTheRootNode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HierarchyInput::create('MGMT', 'Management report')->removeNode('MGMT');
    }

    public function testEditKeepsTheIdsAndTouchedValues(): void
    {
        $child = new HierarchyNode(12, 'R', 'Revenue', 'Net revenue', [HierarchyAccount::profitAndLoss('8000')], [], 3);
        $root = new HierarchyNode(11, 'MGMT', 'Management report', null, [], [$child], 4);
        $input = $root->edit()->change('R', static fn (HierarchyNodeInput $node) => $node->description(''));

        self::assertSame(11, $input->id);
        self::assertSame(4, $input->touched);
        self::assertSame(12, $input->children[0]->id);
        self::assertSame(3, $input->children[0]->touched);
        self::assertNull($input->children[0]->description);
        self::assertEquals($child->accounts, $input->children[0]->accounts);
    }

    public function testRefusesEmptyNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HierarchyNodeInput::create('R', '');
    }

    public function testRefusesAnEmptyAccountCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HierarchyAccount::balanceSheet('');
    }

    public function testRefusesAnEmptyUserCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HierarchyAccessInput::user('', HierarchyAccessType::Edit);
    }

    /**
     * @param list<HierarchyNodeInput> $nodes
     *
     * @return list<string>
     */
    private static function codes(array $nodes): array
    {
        return array_map(static fn (HierarchyNodeInput $node): string => $node->code, $nodes);
    }
}
