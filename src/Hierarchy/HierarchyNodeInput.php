<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use Closure;
use InvalidArgumentException;

/**
 * A node to save with a reporting structure. Immutable: every method returns a new input.
 *
 *   $assets = HierarchyNodeInput::create('A', 'Activa')
 *       ->addChild(HierarchyNodeInput::create('A1', 'Liquide middelen')
 *           ->accounts(HierarchyAccount::balanceSheet('1100'), HierarchyAccount::balanceSheet('1110')));
 *
 * For an existing node, start from `$node->edit()` (or `$hierarchy->edit()`), so the node keeps
 * its ID and Twinfield updates it instead of adding a new one.
 */
final readonly class HierarchyNodeInput
{
    /**
     * @param list<HierarchyAccount> $accounts
     * @param list<HierarchyNodeInput> $children
     */
    private function __construct(
        /** Null for a new node: Twinfield gives it an ID. */
        public ?int $id,
        public string $code,
        public string $name,
        public ?string $description,
        public array $accounts,
        public array $children,
        /** The `touched` value read with the node; 0 for a new node. */
        public int $touched,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('The code of a reporting structure node must not be empty.');
        }

        if ($name === '') {
            throw new InvalidArgumentException(sprintf(
                'The name of reporting structure node %s must not be empty.',
                $code,
            ));
        }
    }

    public static function create(string $code, string $name): self
    {
        return new self(null, $code, $name, null, [], [], 0);
    }

    /**
     * @internal Use HierarchyNode::edit().
     */
    public static function fromNode(HierarchyNode $node): self
    {
        return new self(
            $node->id,
            $node->code,
            $node->name,
            $node->description,
            $node->accounts,
            array_map(self::fromNode(...), $node->children),
            $node->touched,
        );
    }

    public function name(string $name): self
    {
        return new self(
            $this->id,
            $this->code,
            $name,
            $this->description,
            $this->accounts,
            $this->children,
            $this->touched,
        );
    }

    /**
     * Null (or empty) removes it.
     */
    public function description(?string $description): self
    {
        $description = $description === '' ? null : $description;

        return new self(
            $this->id,
            $this->code,
            $this->name,
            $description,
            $this->accounts,
            $this->children,
            $this->touched,
        );
    }

    /**
     * Replaces the accounts of the node. To change the accounts of a node without saving the
     * whole reporting structure, use bind() and unbind().
     */
    public function accounts(HierarchyAccount ...$accounts): self
    {
        return new self(
            $this->id,
            $this->code,
            $this->name,
            $this->description,
            array_values($accounts),
            $this->children,
            $this->touched,
        );
    }

    /**
     * Replaces the child nodes.
     */
    public function children(HierarchyNodeInput ...$children): self
    {
        return new self(
            $this->id,
            $this->code,
            $this->name,
            $this->description,
            $this->accounts,
            array_values($children),
            $this->touched,
        );
    }

    /**
     * Adds child nodes after the existing ones.
     */
    public function addChild(HierarchyNodeInput ...$children): self
    {
        return $this->children(...$this->children, ...$children);
    }

    /**
     * Applies `$change` to this node or the node below it with this code.
     *
     *   $root->change('A1', fn (HierarchyNodeInput $node) => $node->name('Bank'));
     *
     * @param Closure(HierarchyNodeInput): HierarchyNodeInput $change
     *
     * @throws InvalidArgumentException When there is no node with this code.
     */
    public function change(string $code, Closure $change): self
    {
        return $this->changed($code, $change)
            ?? throw new InvalidArgumentException(sprintf('There is no node %s below node %s.', $code, $this->code));
    }

    /**
     * Removes the node below this node with this code, with its child nodes.
     *
     * @throws InvalidArgumentException When there is no node with this code below this node.
     */
    public function remove(string $code): self
    {
        return $this->removed($code)
            ?? throw new InvalidArgumentException(sprintf('There is no node %s below node %s.', $code, $this->code));
    }

    /**
     * @internal
     */
    public function toBody(): Request\NodeBody
    {
        return new Request\NodeBody(
            id: $this->id,
            code: $this->code,
            name: $this->name,
            description: $this->description,
            accounts: Request\AccountList::of(...$this->accounts),
            childNodes: new Request\NodeList(array_map(
                static fn (HierarchyNodeInput $child): Request\NodeBody => $child->toBody(),
                $this->children,
            )),
            touched: $this->touched,
        );
    }

    /**
     * @param Closure(HierarchyNodeInput): HierarchyNodeInput $change
     */
    private function changed(string $code, Closure $change): ?self
    {
        if ($this->code === $code) {
            return $change($this);
        }

        foreach ($this->children as $index => $child) {
            $changed = $child->changed($code, $change);

            if ($changed !== null) {
                $children = $this->children;
                $children[$index] = $changed;

                return $this->children(...$children);
            }
        }

        return null;
    }

    private function removed(string $code): ?self
    {
        foreach ($this->children as $index => $child) {
            $children = $this->children;

            if ($child->code === $code) {
                unset($children[$index]);

                return $this->children(...$children);
            }

            $removed = $child->removed($code);

            if ($removed !== null) {
                $children[$index] = $removed;

                return $this->children(...$children);
            }
        }

        return null;
    }
}
