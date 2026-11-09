<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\DimensionType;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\DimensionType\AddressLabels;
use SolveBeam\Twinfield\DimensionType\CodeMask;
use SolveBeam\Twinfield\DimensionType\DimensionType;
use SolveBeam\Twinfield\DimensionType\DimensionTypeLevels;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class DimensionTypeInputTest extends TestCase
{
    public function testSendsAllValuesOfTheType(): void
    {
        self::assertSame(
            '<dimensiontype><office>1000</office><code>DEB</code><name>Debiteuren</name><mask>1[0-9][0-9][0-9]</mask>'
            . '<address><label1>Tav</label1><label2>Adres</label2><label3/><label4/><label5/><label6/></address>'
            . '</dimensiontype>',
            self::customers()->edit()->toXml('1000')->toXml(),
        );
    }

    public function testChangesNameMaskAndLabels(): void
    {
        $input = self::customers()->edit()
            ->name('Klanten')
            ->mask(CodeMask::any())
            ->addressLabels(new AddressLabels(field4: 'BTW nummer'))
            ->addressLabel(2, null)
            ->addressLabel(6, 'IBAN');

        self::assertSame('Klanten', $input->name);
        self::assertSame('*', $input->mask->pattern);
        self::assertEquals(new AddressLabels(field4: 'BTW nummer', field6: 'IBAN'), $input->addressLabels);
    }

    public function testIsImmutable(): void
    {
        $input = self::customers()->edit();
        $input->name('Klanten');

        self::assertSame('Debiteuren', $input->name);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dimension type name must not be empty.');

        self::customers()->edit()->name('');
    }

    public function testRejectsATooLongName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::customers()->edit()->name(str_repeat('a', 41));
    }

    public function testRejectsATooLongAddressLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address label 6 must be at most 20 characters');

        self::customers()->edit()->addressLabel(6, str_repeat('a', 21));
    }

    private static function customers(): DimensionType
    {
        return new DimensionType(
            company: TestTokens::company('1000'),
            code: 'DEB',
            name: 'Debiteuren',
            shortName: 'Debiteuren',
            mask: new CodeMask('1[0-9][0-9][0-9]'),
            levels: new DimensionTypeLevels(2, 0, 0, 0),
            addressLabels: new AddressLabels('Tav', 'Adres'),
            touched: 1,
        );
    }
}
