<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use Closure;
use InvalidArgumentException;

/**
 * A reporting structure to save, with all its nodes and access rights: Save replaces the whole
 * structure. Immutable: every method returns a new input.
 *
 *   $input = HierarchyInput::create('MGMT', 'Management report')
 *       ->addNode(HierarchyNodeInput::create('R', 'Revenue')->accounts(HierarchyAccount::profitAndLoss('8000')))
 *       ->addNode(HierarchyNodeInput::create('C', 'Costs'))
 *       ->addNode(
 *           HierarchyNodeInput::create('C1', 'Staff')->accounts(HierarchyAccount::profitAndLoss('4000')),
 *           parent: 'C',
 *       )
 *       ->accessRights(
 *           HierarchyAccessInput::user('SUPER', HierarchyAccessType::Edit),
 *           HierarchyAccessInput::everyone(HierarchyAccessType::Use),
 *       );
 *
 * For an existing reporting structure, start from `$hierarchy->edit()`, so the nodes keep their
 * IDs and all current values are kept.
 */
final readonly class HierarchyInput
{
    /**
     * @param list<HierarchyAccessInput> $accessRights
     */
    private function __construct(
        public string $code,
        public string $name,
        public ?string $description,
        public HierarchyNodeInput $rootNode,
        public array $accessRights,
        /** The `touched` value read with the reporting structure; 0 for a new one. */
        public int $touched,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('The code of a reporting structure must not be empty.');
        }

        if ($name === '') {
            throw new InvalidArgumentException(sprintf('The name of reporting structure %s must not be empty.', $code));
        }
    }

    /**
     * A new reporting structure. The root node gets the code and name of the structure, as in
     * the reporting structures Twinfield returns.
     */
    public static function create(string $code, string $name): self
    {
        return new self($code, $name, null, HierarchyNodeInput::create($code, $name), [], 0);
    }

    /**
     * @internal Use Hierarchy::edit().
     */
    public static function fromHierarchy(Hierarchy $hierarchy): self
    {
        return new self(
            $hierarchy->code,
            $hierarchy->name,
            $hierarchy->description,
            $hierarchy->rootNode->edit(),
            array_map(
                static fn (HierarchyAccess $access): HierarchyAccessInput => $access->edit(),
                $hierarchy->accessRights,
            ),
            $hierarchy->touched,
        );
    }

    /**
     * The name of the reporting structure. The name of the root node stays the same; change it
     * with `changeNode($input->code, …)`.
     */
    public function name(string $name): self
    {
        return new self($this->code, $name, $this->description, $this->rootNode, $this->accessRights, $this->touched);
    }

    /**
     * Null (or empty) removes it.
     */
    public function description(?string $description): self
    {
        $description = $description === '' ? null : $description;

        return new self($this->code, $this->name, $description, $this->rootNode, $this->accessRights, $this->touched);
    }

    public function rootNode(HierarchyNodeInput $rootNode): self
    {
        return new self($this->code, $this->name, $this->description, $rootNode, $this->accessRights, $this->touched);
    }

    /**
     * Adds a node at the end of the child nodes of `$parent` (by code; default the root node).
     *
     * @throws InvalidArgumentException When there is no node `$parent`.
     */
    public function addNode(HierarchyNodeInput $node, ?string $parent = null): self
    {
        return $this->changeNode(
            $parent ?? $this->rootNode->code,
            static fn (HierarchyNodeInput $parentNode): HierarchyNodeInput => $parentNode->addChild($node),
        );
    }

    /**
     * Applies `$change` to the node with this code, the root node included.
     *
     *   $input->changeNode('A1', fn (HierarchyNodeInput $node) => $node->addChild(...));
     *
     * @param Closure(HierarchyNodeInput): HierarchyNodeInput $change
     *
     * @throws InvalidArgumentException When there is no node with this code.
     */
    public function changeNode(string $code, Closure $change): self
    {
        return $this->rootNode($this->rootNode->change($code, $change));
    }

    /**
     * Removes the node with this code, with its child nodes.
     *
     * @throws InvalidArgumentException When there is no node with this code below the root node.
     */
    public function removeNode(string $code): self
    {
        return $this->rootNode($this->rootNode->remove($code));
    }

    /**
     * Replaces the access rights.
     */
    public function accessRights(HierarchyAccessInput ...$accessRights): self
    {
        return new self(
            $this->code,
            $this->name,
            $this->description,
            $this->rootNode,
            array_values($accessRights),
            $this->touched,
        );
    }

    /**
     * @internal
     */
    public function toBody(): Request\HierarchyBody
    {
        return new Request\HierarchyBody(
            code: $this->code,
            name: $this->name,
            description: $this->description,
            rootNode: $this->rootNode->toBody(),
            accessRights: new Request\AccessList(array_map(
                static fn (HierarchyAccessInput $access): Request\AccessBody => $access->toBody(),
                $this->accessRights,
            )),
            touched: $this->touched,
        );
    }
}
