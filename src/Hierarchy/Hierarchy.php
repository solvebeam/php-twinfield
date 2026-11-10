<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use IteratorAggregate;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A reporting structure (rapportagestructuur): a tree of nodes with general ledger accounts, e.g.
 * RGS. The nodes are the same for every company of the organisation; the accounts bound to them
 * are those of `$company`.
 *
 *   $hierarchy = $twinfield->company('1000')->hierarchies()->get('RGS');
 *
 *   foreach (new RecursiveTreeIterator($hierarchy) as $line) {
 *       echo $line, PHP_EOL;
 *   }
 *
 * Iterating gives the root node, recursively its child nodes and accounts (HierarchyNode and
 * HierarchyAccount objects). Use nodes() for the nodes only.
 *
 * @implements IteratorAggregate<int, HierarchyNode|HierarchyAccount>
 */
final readonly class Hierarchy implements IteratorAggregate
{
    /**
     * @param list<HierarchyAccess> $accessRights
     */
    public function __construct(
        /** Company the accounts belong to, with its organisation. */
        public CompanyReference $company,
        public string $code,
        public string $name,
        /** Null when empty. */
        public ?string $description,
        /** The top node, with the code and name of the reporting structure. */
        public HierarchyNode $rootNode,
        public array $accessRights,
        /** The number of times the reporting structure was changed; Twinfield checks it on save. */
        public int $touched,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResponse(XmlElement $hierarchy, CompanyReference $company): self
    {
        return new self(
            company: $company,
            code: $hierarchy->string('Code'),
            name: $hierarchy->string('Name'),
            description: $hierarchy->nullableString('Description'),
            rootNode: HierarchyNode::fromResponse($hierarchy->child('RootNode')),
            accessRights: array_map(
                HierarchyAccess::fromResponse(...),
                $hierarchy->child('AccessRights')->list('HierarchyAccess'),
            ),
            touched: $hierarchy->int('Touched'),
        );
    }

    /**
     * The node with this code, the root node included; null when there is none.
     */
    public function node(string $code): ?HierarchyNode
    {
        return $this->rootNode->node($code);
    }

    /**
     * All nodes, the root node included, in tree order (depth first).
     *
     * @return non-empty-list<HierarchyNode>
     */
    public function allNodes(): array
    {
        return $this->rootNode->allNodes();
    }

    /**
     * The root node and, recursively, its child nodes, without accounts:
     *
     *   foreach (new RecursiveTreeIterator($hierarchy->nodes()) as $line) { … }
     *   $nodes = new RecursiveIteratorIterator($hierarchy->nodes(), RecursiveIteratorIterator::SELF_FIRST);
     *
     *   foreach ($nodes as $node) { … }
     */
    public function nodes(): HierarchyNodeIterator
    {
        return new HierarchyNodeIterator([$this->rootNode]);
    }

    /**
     * The root node and, recursively, the accounts and child nodes of every node.
     */
    public function getIterator(): HierarchyNodeAccountIterator
    {
        return new HierarchyNodeAccountIterator([$this->rootNode]);
    }

    /**
     * Input with all values of this reporting structure, to change and pass to save(). Keeps the
     * node IDs and `touched` values, so Twinfield updates the nodes and refuses the save when
     * someone else changed the structure in the meantime.
     *
     *   $hierarchies->save($hierarchy->edit()->changeNode('1', fn ($node) => $node->name('Assets')));
     */
    public function edit(): HierarchyInput
    {
        return HierarchyInput::fromHierarchy($this);
    }
}
