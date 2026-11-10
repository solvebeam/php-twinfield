<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Hierarchy;

use RecursiveTreeIterator;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessType;
use SolveBeam\Twinfield\Hierarchy\HierarchyNode;
use SolveBeam\Twinfield\Hierarchy\HierarchyNodeSummary;
use SolveBeam\Twinfield\Hierarchy\HierarchyNotFoundException;
use SolveBeam\Twinfield\Hierarchy\HierarchyResource;
use SolveBeam\Twinfield\Hierarchy\HierarchySummary;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live reporting structure queries (read-only). Saving, binding, hiding and deleting are in the
 * write tests.
 */
final class HierarchyResourceTest extends IntegrationTestCase
{
    public function testSearchesTheReportingStructuresOfTheOrganisation(): void
    {
        $summaries = iterator_to_array($this->hierarchies()->search()->all(), false);

        self::assertNotEmpty($summaries, 'The organisation has reporting structures.');

        foreach ($summaries as $summary) {
            self::assertTrue($summary->organisation->equals($this->twinfield->organisation()));
        }
    }

    public function testReadsEveryReportingStructure(): void
    {
        foreach ($this->summaries() as $summary) {
            $hierarchy = $this->hierarchies()->get($summary->code);

            self::assertSame($summary->code, $hierarchy->code);
            self::assertSame($summary->name, $hierarchy->name);
            self::assertSame($this->twinfield->companyCode(), $hierarchy->company->code);
            self::assertSame(
                $hierarchy->code,
                $hierarchy->rootNode->code,
                'The root node has the code of the reporting structure.',
            );
            self::assertNotEmpty($hierarchy->accessRights);

            $nodes = $hierarchy->allNodes();
            $codes = array_map(static fn (HierarchyNode $node): string => $node->code, $nodes);

            self::assertSame($codes, array_unique($codes), 'Node codes are unique within the structure.');

            foreach ($nodes as $node) {
                self::assertFalse(
                    $node->accounts !== [] && $node->children !== [],
                    sprintf('Node %s has either accounts or child nodes.', $node->code),
                );
                self::assertSame($node, $hierarchy->node($node->code));
            }

            $lines = iterator_to_array(new RecursiveTreeIterator($hierarchy), false);

            self::assertCount(count($nodes) + count($hierarchy->rootNode->allAccounts()), $lines);
            self::assertSame('\\-' . $hierarchy->rootNode, $lines[0]);
        }
    }

    public function testTheLastAccessRightIsTheOneOfEveryoneElse(): void
    {
        foreach ($this->summaries() as $summary) {
            $accessRights = $this->hierarchies()->get($summary->code)->accessRights;
            $everyone = $accessRights[count($accessRights) - 1];

            self::assertNull($everyone->userCode);
            self::assertNull($everyone->roleCode);
            self::assertNull($everyone->officeCode);
            self::assertFalse($everyone->accessRightTabAccessible);
            self::assertContains($everyone->type, [HierarchyAccessType::None, HierarchyAccessType::Use]);
        }
    }

    public function testSearchesTheNodesOfAReportingStructure(): void
    {
        $summary = $this->summaries()[0];
        $hierarchy = $this->hierarchies()->get($summary->code);
        $nodes = iterator_to_array($this->hierarchies()->searchNodes($summary->code)->all(), false);
        $expected = array_map(static fn (HierarchyNode $node): string => $node->code, $hierarchy->allNodes());
        $found = array_map(static fn (HierarchyNodeSummary $node): string => $node->code, $nodes);

        sort($expected);
        sort($found);

        // Every node, the root included, but not in tree order (nor by code or ID).
        self::assertSame($expected, $found);

        foreach ($nodes as $node) {
            self::assertSame($summary->code, $node->hierarchyCode);
            self::assertSame($node->name, $hierarchy->node($node->code)?->name);
        }
    }

    public function testUnknownCodeIsNotFound(): void
    {
        $this->expectException(HierarchyNotFoundException::class);

        $this->hierarchies()->get('SDK-DOES-NOT-EXIST');
    }

    private function hierarchies(): HierarchyResource
    {
        return $this->twinfield->hierarchies();
    }

    /**
     * @return non-empty-list<HierarchySummary>
     */
    private function summaries(): array
    {
        $summaries = iterator_to_array($this->hierarchies()->search()->all(), false);

        if ($summaries === []) {
            self::markTestSkipped('The organisation has no reporting structures.');
        }

        return $summaries;
    }
}
