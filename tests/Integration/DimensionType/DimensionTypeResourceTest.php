<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\DimensionType;

use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\DimensionType\DimensionTypeNotFoundException;
use SolveBeam\Twinfield\DimensionType\DimensionTypeSummary;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live dimension type searches and reads (read-only) against TWINFIELD_COMPANY.
 *
 * A `<dimensiontype>` with only office and code reads the type; the write guard allows it.
 */
final class DimensionTypeResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsEveryDimensionType(): void
    {
        $types = $this->twinfield->dimensionTypes();
        $codes = [];

        foreach ($types->search()->all() as $summary) {
            self::assertInstanceOf(DimensionTypeSummary::class, $summary);

            $type = $types->get($summary->code);

            self::assertSame($summary->code, $type->code);
            self::assertSame($summary->name, $type->name);
            self::assertGreaterThan(0, $type->levels->financial, 'Every type has a financial level.');
            $codes[] = $type->code;
        }

        foreach (DimensionTypeCode::cases() as $standard) {
            self::assertContains($standard->value, $codes, 'Every company has the standard types.');
        }
    }

    public function testSearchesByFinancialLevel(): void
    {
        $codes = array_map(
            static fn (DimensionTypeSummary $summary): string => $summary->code,
            iterator_to_array($this->twinfield->dimensionTypes()->search()->financialLevel(1)->all(), false),
        );
        sort($codes);

        self::assertSame(['BAS', 'PNL'], $codes);
    }

    public function testSearchesTheTypesOfADimension(): void
    {
        $customer = $this->twinfield->customers()->search()->limit(1)->get()->items[0] ?? null;

        if ($customer === null) {
            self::markTestSkipped('The company has no customers.');
        }

        $codes = array_map(
            static fn (DimensionTypeSummary $summary): string => $summary->code,
            iterator_to_array($this->twinfield->dimensionTypes()->search()->ofDimension($customer->code)->all(), false),
        );

        self::assertContains('DEB', $codes);
    }

    public function testThrowsWhenTheDimensionTypeDoesNotExist(): void
    {
        $types = $this->twinfield->dimensionTypes();

        self::assertFalse($types->exists('XYZ'));

        $this->expectException(DimensionTypeNotFoundException::class);

        $types->get('XYZ');
    }
}
