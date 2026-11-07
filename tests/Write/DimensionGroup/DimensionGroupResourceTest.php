<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\DimensionGroup;

use SolveBeam\Twinfield\CostCenter\CostCenterSummary;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupAlreadyExistsException;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupInput;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupNotFoundException;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupStatus;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Reads every dimension group, and creates, updates and deletes a temporary group
 * (EXAMPLE-999 or the like); always deletes it again.
 *
 * Also checks the Twinfield behaviour the SDK relies on, which its documentation leaves
 * open: a save replaces all dimensions, mixed dimension types are allowed, a deleted group
 * is not in the Finder, and its code can be used again.
 */
final class DimensionGroupResourceTest extends WriteTestCase
{
    public function testReadsEveryDimensionGroup(): void
    {
        $groups = $this->twinfield->dimensionGroups();
        $count = 0;

        foreach ($groups->search()->all() as $summary) {
            $group = $groups->get($summary->code);

            self::assertSame($summary->code, $group->code);
            self::assertSame($summary->name, $group->name);
            self::assertSame($this->company(), $group->company);
            self::assertSame(DimensionGroupStatus::Active, $group->status);
            $count++;
        }

        if ($count === 0) {
            self::markTestSkipped('The company has no dimension groups.');
        }
    }

    public function testLifecycle(): void
    {
        $groups = $this->twinfield->dimensionGroups();
        $code = Example::unusedDimensionGroupCode($this->twinfield);
        [$first, $second, $third] = Example::customerCodes($this->twinfield, 3);
        $costCenter = $this->costCenterCode();

        try {
            $created = $groups->create(
                DimensionGroupInput::create($code, 'SDK test group & more')
                    ->shortName('SDK test')
                    ->add('DEB', $first, $second),
            );

            self::assertSame($code, $created->code);
            self::assertSame('SDK test group & more', $created->name);
            self::assertSame('SDK test', $created->shortName);
            self::assertSame(DimensionGroupStatus::Active, $created->status);
            self::assertSame([$first, $second], $created->codes('DEB'));
            self::assertNotNull($created->dimensions[0]->name, 'Twinfield adds the dimension name.');
            self::assertTrue($groups->exists($code));

            try {
                $groups->create(DimensionGroupInput::create($code, 'Duplicate'));
                self::fail('Expected a DimensionGroupAlreadyExistsException.');
            } catch (DimensionGroupAlreadyExistsException) {
                self::assertSame('SDK test group & more', $groups->get($code)->name, 'The existing one is unchanged.');
            }

            // A save replaces all dimensions; dimensions of more than one type are allowed.
            $updated = $groups->update(
                $created->edit()
                    ->name('SDK test group')
                    ->add('DEB', $third)
                    ->remove('DEB', $first)
                    ->add('KPL', $costCenter),
            );

            self::assertSame('SDK test group', $updated->name);
            self::assertSame([$second, $third], $updated->codes('DEB'));
            self::assertSame([$costCenter], $updated->codes('KPL'));
            self::assertEquals($updated->dimensions, $groups->get($code)->dimensions, 'A read returns what was saved.');

            // The Finder option dimtype finds groups with at least one dimension of that type.
            self::assertTrue($this->foundByType($code, 'DEB'));
            self::assertTrue($this->foundByType($code, 'KPL'));
            self::assertFalse($this->foundByType($code, 'CRD'));

            try {
                $groups->save($updated->edit()->add('DEB', 'ZZ99999'));
                self::fail('Expected a ProcessXmlException for an unknown dimension.');
            } catch (ProcessXmlException $exception) {
                // The fourth dimension: with more than one, the path has the position.
                self::assertSame('dimensiongroup/dimensions/dimension[4]/code', $exception->result->errors()[0]->path);
            }

            $cleared = $groups->update($updated->edit()->clear()->shortName(null));

            self::assertSame([], $cleared->dimensions);
            self::assertNull($cleared->shortName);

            $groups->delete($code);

            self::assertFalse($groups->exists($code), 'A deleted group is not in the Finder.');
            self::assertNull($groups->find($code));

            // The code of a deleted group can be used again, and is active then.
            $again = $groups->create(DimensionGroupInput::create($code, 'SDK test group again')->add('DEB', $first));

            self::assertSame(DimensionGroupStatus::Active, $again->status);
            self::assertSame([$first], $again->codes());
            self::assertTrue($groups->exists($code));
        } finally {
            if ($groups->exists($code)) {
                $groups->delete($code);
            }
        }
    }

    public function testDoesNotTouchAnUnknownGroup(): void
    {
        $groups = $this->twinfield->dimensionGroups();
        $code = Example::unusedDimensionGroupCode($this->twinfield);

        try {
            $groups->delete($code);
            self::fail('Expected a DimensionGroupNotFoundException.');
        } catch (DimensionGroupNotFoundException) {
            self::assertFalse($groups->exists($code), 'delete() did not create the group.');
        }
    }

    private function foundByType(string $code, string $type): bool
    {
        $search = $this->twinfield->dimensionGroups()->search($code)->inCode()->dimensionType($type);

        foreach ($search->get() as $summary) {
            if ($summary->code === $code) {
                return true;
            }
        }

        return false;
    }

    private function costCenterCode(): string
    {
        $costCenter = $this->twinfield->costCenters()->search()->limit(1)->get()->items[0] ?? null;

        if (!$costCenter instanceof CostCenterSummary) {
            self::markTestSkipped('The company has no cost centers.');
        }

        return $costCenter->code;
    }
}
