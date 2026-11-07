<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\DimensionType;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\DimensionType\AddressLabels;

final class AddressLabelsTest extends TestCase
{
    public function testGivesTheLabelPerField(): void
    {
        $labels = new AddressLabels('Tav', 'Adres', null, 'BTW nummer');

        self::assertSame('Tav', $labels->forField(1));
        self::assertNull($labels->forField(3));
        self::assertSame([1 => 'Tav', 2 => 'Adres', 4 => 'BTW nummer'], $labels->used());
    }

    public function testChangesOneLabel(): void
    {
        $labels = new AddressLabels('Tav', 'Adres');

        $changed = $labels->with(6, 'IBAN')->with(1, '');

        self::assertEquals(new AddressLabels(null, 'Adres', null, null, null, 'IBAN'), $changed);
        self::assertSame('Tav', $labels->field1, 'Immutable.');
    }

    public function testCreatesFromAnArray(): void
    {
        self::assertEquals(new AddressLabels(field2: 'Adres', field5: 'KVK'), AddressLabels::fromArray([2 => 'Adres', 5 => 'KVK']));
    }

    public function testRejectsAFieldOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address field must be 1 to 6, 7 given.');

        (new AddressLabels())->forField(7);
    }
}
