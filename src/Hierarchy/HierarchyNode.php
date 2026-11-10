<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use IteratorAggregate;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use Stringable;

/**
 * A node (level) of a reporting structure, with the general ledger accounts bound to it and its
 * child nodes. Live, a node has either accounts or child nodes, never both.
 *
 * Iterating a node gives its accounts and child nodes, recursively with RecursiveIteratorIterator
 * or RecursiveTreeIterator:
 *
 *   foreach (new RecursiveTreeIterator($hierarchy->node('1')) as $line) {
 *       echo $line, PHP_EOL;
 *   }
 *
 * @implements IteratorAggregate<int, HierarchyNode|HierarchyAccount>
 */
final readonly class HierarchyNode implements IteratorAggregate, Stringable
{
    /**
     * @param list<HierarchyAccount> $accounts Accounts of the company the reporting structure was
     *                                         read for.
     * @param list<HierarchyNode> $children
     */
    public function __construct(
        /** Internal ID of the node, for bind() and unbind(). */
        public int $id,
        /** Unique within the reporting structure. The root node has the code of the structure. */
        public string $code,
        public string $name,
        /** Null when empty. */
        public ?string $description,
        public array $accounts,
        public array $children,
        /** The number of times the node was changed; Twinfield checks it on save. */
        public int $touched,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResponse(XmlElement $node): self
    {
        return new self(
            id: $node->int('Id'),
            code: $node->string('Code'),
            name: $node->string('Name'),
            description: $node->nullableString('Description'),
            accounts: array_map(
                HierarchyAccount::fromResponse(...),
                $node->child('Accounts')->list('HierarchyAccount'),
            ),
            children: array_map(self::fromResponse(...), $node->child('ChildNodes')->list('HierarchyNode')),
            touched: $node->int('Touched'),
        );
    }

    /**
     * This node or a node below it, by code; null when there is none.
     */
    public function node(string $code): ?self
    {
        if ($this->code === $code) {
            return $this;
        }

        foreach ($this->children as $child) {
            $node = $child->node($code);

            if ($node !== null) {
                return $node;
            }
        }

        return null;
    }

    /**
     * This node and all nodes below it, in tree order (depth first).
     *
     * @return non-empty-list<HierarchyNode>
     */
    public function allNodes(): array
    {
        $nodes = [$this];

        foreach ($this->children as $child) {
            $nodes = [...$nodes, ...$child->allNodes()];
        }

        return $nodes;
    }

    /**
     * The accounts of this node and of all nodes below it, in tree order.
     *
     * @return list<HierarchyAccount>
     */
    public function allAccounts(): array
    {
        $accounts = $this->accounts;

        foreach ($this->children as $child) {
            $accounts = [...$accounts, ...$child->allAccounts()];
        }

        return $accounts;
    }

    /**
     * The child nodes, without accounts, recursively with RecursiveIteratorIterator.
     */
    public function nodes(): HierarchyNodeIterator
    {
        return new HierarchyNodeIterator($this->children);
    }

    /**
     * The accounts, then the child nodes.
     */
    public function getIterator(): HierarchyNodeAccountIterator
    {
        return new HierarchyNodeAccountIterator([...$this->accounts, ...$this->children]);
    }

    /**
     * Input with all values of this node and the nodes below it, see Hierarchy::edit().
     */
    public function edit(): HierarchyNodeInput
    {
        return HierarchyNodeInput::fromNode($this);
    }

    /**
     * E.g. `1 Activa`, for RecursiveTreeIterator.
     */
    public function __toString(): string
    {
        return $this->code . ' ' . $this->name;
    }
}
