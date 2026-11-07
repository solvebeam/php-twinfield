<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionGroup;

use LogicException;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Dimension groups of the company: named sets of dimensions, e.g. a group of customers or
 * of cost centers, to filter dimension searches and reports on.
 *
 *   $groups = $twinfield->company('1000')->dimensionGroups();
 *
 *   foreach ($groups->search()->dimensionType('DEB')->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $group = $groups->get('NL-CUSTOMERS');
 *
 *   $groups->create(DimensionGroupInput::create('KEY-ACCOUNTS', 'Key accounts')->add('DEB', '1000', '1001'));
 *   $groups->update($group->edit()->add('DEB', '1003')->remove('DEB', '1000'));
 *   $groups->delete('KEY-ACCOUNTS');
 *
 * Twinfield has no `<read>` for dimension groups, and a `<dimensiongroup>` request for an
 * unknown code creates it (named after its code), also when it only reads or deletes. So
 * get(), update() and delete() first check in the Finder that the code exists: a read
 * never creates a group.
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/dimension-groups/
 */
final readonly class DimensionGroupResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search dimension groups by code and name, e.g. `search('NL*')->inCode()->get()`, or
     * only the groups of one dimension type: `search()->dimensionType('DEB')`.
     *
     * Finder type `GRP`; the company comes from the client, so no `office` option is sent.
     * Deleted groups are not in the results.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<DimensionGroupSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        $query = $this->twinfield->finder(FinderType::DimensionGroups);

        return (new CodeNameSearch($query, DimensionGroupSummary::fromFinderRow(...)))->pattern($pattern);
    }

    /**
     * Whether the company has an active dimension group with exactly this code (one Finder request).
     */
    public function exists(string $code): bool
    {
        foreach ($this->search($code)->inCode()->get() as $summary) {
            if ($summary->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * The group with its dimensions. Two requests: a Finder check, then the group itself.
     *
     * @throws DimensionGroupNotFoundException When there is no (active) group with this code.
     */
    public function get(string $code): DimensionGroup
    {
        $this->assertExists($code);

        $xml = XmlBuilder::create('dimensiongroup');
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $code);

        return $this->send($xml);
    }

    /**
     * The group, or null when there is no (active) group with this code.
     */
    public function find(string $code): ?DimensionGroup
    {
        try {
            return $this->get($code);
        } catch (DimensionGroupNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new group. Checks first, so an existing group is never overwritten.
     *
     * The code of a deleted group can be used again: Twinfield then makes it active with
     * the values of the input.
     *
     * @throws DimensionGroupAlreadyExistsException When the code is already in use.
     * @throws ProcessXmlException When Twinfield refuses the group, e.g. an unknown dimension.
     */
    public function create(DimensionGroupInput $group): DimensionGroup
    {
        if ($this->exists($group->code)) {
            throw new DimensionGroupAlreadyExistsException($group->code);
        }

        return $this->save($group);
    }

    /**
     * Updates an existing group with all values of the input. Start from `$group->edit()`,
     * so the dimensions you do not change are sent as they are.
     *
     * @throws DimensionGroupNotFoundException When there is no (active) group with this code.
     * @throws ProcessXmlException When Twinfield refuses the group, e.g. an unknown dimension.
     */
    public function update(DimensionGroupInput $group): DimensionGroup
    {
        $this->assertExists($group->code);

        return $this->save($group);
    }

    /**
     * Creates the group, or replaces it when the code exists (one request, no check).
     *
     * @throws ProcessXmlException When Twinfield refuses the group, e.g. an unknown dimension.
     */
    public function save(DimensionGroupInput $group): DimensionGroup
    {
        return $this->send($group->toXml($this->company()));
    }

    /**
     * Deletes the group. Three requests: a Finder check, emptying the group, and the delete.
     *
     * Twinfield keeps the code of a deleted group on its dimensions (e.g. in the groups of a
     * customer), so the group is emptied first: afterwards no dimension refers to it.
     *
     * @throws DimensionGroupNotFoundException When there is no (active) group with this code.
     */
    public function delete(string $code): void
    {
        $this->assertExists($code);

        // Only <dimensions/>: Twinfield keeps the name and short name.
        $empty = XmlBuilder::create('dimensiongroup');
        $empty->add($empty->root(), 'office', $this->company());
        $empty->add($empty->root(), 'code', $code);
        $empty->add($empty->root(), 'dimensions');

        if ($this->send($empty)->dimensions !== []) {
            throw new UnexpectedResponseException(sprintf(
                'Dimension group %s still has dimensions after emptying it.',
                $code,
            ));
        }

        $xml = XmlBuilder::create('dimensiongroup', ['status' => DimensionGroupStatus::Deleted->value]);
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $code);

        $group = $this->send($xml);

        if ($group->status !== DimensionGroupStatus::Deleted) {
            throw new UnexpectedResponseException(sprintf(
                'Dimension group %s is still %s after deleting it.',
                $code,
                $group->status->value,
            ));
        }
    }

    private function assertExists(string $code): void
    {
        if (!$this->exists($code)) {
            throw new DimensionGroupNotFoundException($code);
        }
    }

    private function send(XmlBuilder $xml): DimensionGroup
    {
        $root = XmlElement::root($this->twinfield->processXml($xml->toXml()));

        if ($root->element->localName !== 'dimensiongroup') {
            throw new UnexpectedResponseException(sprintf(
                'Expected <dimensiongroup>, got <%s>.',
                $root->element->localName,
            ));
        }

        return DimensionGroup::fromXml($root);
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Dimension groups belong to a company: use company() first.');
    }
}
