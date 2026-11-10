<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\CostCenter;

use SolveBeam\Twinfield\CostCenter\CostCenterInput;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates, updates, deletes and reactivates a temporary cost center (999 or the like);
 * always deletes it again.
 *
 * The code format of cost centers differs per company, so the code follows the length of
 * an existing numeric code: see Example::unusedCostCenterCode().
 */
final class CostCenterResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $costCenters = $this->twinfield->costCenters();
        $code = Example::unusedCostCenterCode($this->twinfield);

        try {
            $created = $costCenters->create(CostCenterInput::create($code, 'SDK test cost center'));

            self::assertSame($code, $created->code);
            self::assertSame(DimensionStatus::Active, $created->status);
            self::assertFalse($created->inUse);

            $read = $costCenters->get($code);

            self::assertSame('SDK test cost center', $read->name);
            self::assertSame($created->uid, $read->uid);

            try {
                $costCenters->create(CostCenterInput::create($code, 'Duplicate'));
                self::fail('Expected a DimensionAlreadyExistsException.');
            } catch (DimensionAlreadyExistsException) {
                self::assertSame(
                    'SDK test cost center',
                    $costCenters->get($code)->name,
                    'The existing cost center is unchanged.',
                );
            }

            $updated = $costCenters->update($read->edit()->name('SDK test cost center (updated) & more'));

            self::assertSame('SDK test cost center (updated) & more', $updated->name);
            self::assertSame('SDK test cost center (updated) & more', $costCenters->get($code)->name);

            self::assertSame(DeleteResult::Deleted, $costCenters->delete($code));
            self::assertSame(DimensionStatus::Deleted, $costCenters->get($code)->status);

            $costCenters->activate($code);
            self::assertSame(DimensionStatus::Active, $costCenters->get($code)->status);
        } finally {
            if ($costCenters->find($code)?->status === DimensionStatus::Active) {
                $costCenters->delete($code);
            }
        }
    }
}
