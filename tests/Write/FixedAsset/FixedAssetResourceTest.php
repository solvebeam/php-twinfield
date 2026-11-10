<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\FixedAsset;

use DateTimeImmutable;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\FixedAsset\FixedAssetField;
use SolveBeam\Twinfield\FixedAsset\FixedAssetInput;
use SolveBeam\Twinfield\FixedAsset\FixedAssetStatus;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\Period\Period;

/**
 * Creates a temporary fixed asset with the next free code, changes it, deletes and reactivates
 * it; always deletes it again.
 *
 * This also confirms what Twinfield does with an empty number of periods and which settings it
 * locks on a new asset.
 */
final class FixedAssetResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $fixedAssets = $this->twinfield->fixedAssets();
        $method = Example::assetMethodCode($this->twinfield);
        $costCenter = Example::costCenterCode($this->twinfield);
        $code = null;

        try {
            $created = $fixedAssets->create(
                FixedAssetInput::create('SDK test asset')
                    ->method($method)
                    ->costCenter($costCenter)
                    ->purchaseDate(new DateTimeImmutable('2026-03-15'))
                    ->depreciateFrom(Period::of(2026, 4))
                    ->periods(60)
                    ->residualValue('2500.00')
                    ->freeText(1, 'SN-12345'),
            );
            $code = $created->code;

            self::assertSame(DimensionStatus::Active, $created->status);
            self::assertSame(FixedAssetStatus::ToBeActivated, $created->assetStatus);
            self::assertFalse($created->inUse);

            $read = $fixedAssets->get($code);

            self::assertSame('SDK test asset', $read->name);
            self::assertSame($created->uid, $read->uid);
            self::assertSame($method, $read->method?->code);
            self::assertSame($costCenter, $read->costCenter->code);
            self::assertEquals(new DateTimeImmutable('2026-03-15'), $read->purchaseDate);
            self::assertSame('2026/04', (string) $read->depreciateFrom);
            self::assertSame(60, $read->periods);
            self::assertFalse($read->periodsInherited);
            self::assertSame('2500.00', $read->residualValue->value);
            self::assertSame('SN-12345', $read->freeText(1));
            self::assertSame([], $read->transactionLines);
            self::assertSame([FixedAssetField::Status, FixedAssetField::SellDate], $read->lockedFields);

            try {
                $fixedAssets->create(FixedAssetInput::create('Duplicate')->code($code));
                self::fail('Expected a DimensionAlreadyExistsException.');
            } catch (DimensionAlreadyExistsException) {
                self::assertSame('SDK test asset', $fixedAssets->get($code)->name, 'The existing asset is unchanged.');
            }

            $updated = $fixedAssets->update(
                $read->edit()
                    ->name('SDK test asset (updated) & more')
                    ->residualValue('1000.00')
                    ->freeText(1, null)
                    ->freeText(2, 'Lease 2026-001'),
            );

            self::assertSame('SDK test asset (updated) & more', $updated->name);

            $read = $fixedAssets->get($code);

            self::assertSame('1000.00', $read->residualValue->value);
            self::assertNull($read->freeText(1), 'null clears a free text.');
            self::assertSame('Lease 2026-001', $read->freeText(2));
            self::assertSame(60, $read->periods, 'What is not changed stays.');

            // An empty number of periods: see what Twinfield makes of it.
            $read = $fixedAssets->update($read->edit()->periods(null));

            self::assertTrue(
                $read->periods === null || $read->periodsInherited,
                'An empty number of periods is empty or taken from the asset method.',
            );

            self::assertSame(DeleteResult::Deleted, $fixedAssets->delete($code));
            self::assertSame(DimensionStatus::Deleted, $fixedAssets->get($code)->status);

            $fixedAssets->activate($code);
            self::assertSame(DimensionStatus::Active, $fixedAssets->get($code)->status);
        } finally {
            if ($code !== null && $fixedAssets->find($code)?->status === DimensionStatus::Active) {
                $fixedAssets->delete($code);
            }
        }
    }
}
