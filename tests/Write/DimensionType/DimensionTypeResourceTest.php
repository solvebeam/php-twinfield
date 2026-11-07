<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\DimensionType;

use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Changes the name and an address label of the suppliers type (CRD), and always restores
 * them. Also checks what the SDK relies on: an update answers with the whole type, and a
 * read sends nothing that changes the type.
 */
final class DimensionTypeResourceTest extends WriteTestCase
{
    public function testUpdatesAndRestoresADimensionType(): void
    {
        $types = $this->twinfield->dimensionTypes();
        $original = $types->get(DimensionTypeCode::AccountsPayable);

        try {
            $updated = $types->update(
                $original->edit()
                    ->name('SDK test suppliers')
                    ->addressLabel(6, 'SDK test label'),
            );

            self::assertSame('SDK test suppliers', $updated->name);
            self::assertSame('SDK test label', $updated->addressLabels->field6);
            self::assertEquals($original->mask, $updated->mask, 'The mask is sent unchanged.');
            self::assertEquals($original->levels, $updated->levels);
            self::assertSame($original->shortName, $updated->shortName, 'The short name is read-only.');

            $read = $types->get(DimensionTypeCode::AccountsPayable);

            self::assertEquals($updated->addressLabels, $read->addressLabels, 'The update response is the saved type.');
            self::assertSame($updated->name, $read->name);
        } finally {
            $restored = $types->update($original->edit());

            self::assertSame($original->name, $restored->name);
            self::assertEquals($original->addressLabels, $restored->addressLabels);
        }
    }
}
