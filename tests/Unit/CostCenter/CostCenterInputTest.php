<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\CostCenter;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\CostCenter\CostCenter;
use SolveBeam\Twinfield\CostCenter\CostCenterInput;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class CostCenterInputTest extends TestCase
{
    public function testIsImmutable(): void
    {
        $base = CostCenterInput::create('100', 'Sales');
        $changed = $base->name('Marketing');

        self::assertSame('Sales', $base->name);
        self::assertSame('Marketing', $changed->name);
        self::assertSame('100', $changed->code);
    }

    public function testAcceptsANameOf80Characters(): void
    {
        self::assertSame(80, mb_strlen(CostCenterInput::create('100', str_repeat('é', 80))->name));
    }

    public function testBuildsTheRequest(): void
    {
        self::assertSame(
            '<dimension><office>1000</office><type>KPL</type><code>100</code><name>Sales &amp; marketing</name></dimension>',
            CostCenterInput::create('100', 'Sales & marketing')->toXml('1000')->toXml(),
        );
    }

    public function testEditKeepsCodeAndName(): void
    {
        $costCenter = new CostCenter(
            company: TestTokens::company('1000'),
            code: '100',
            uid: '5f3c2a1e-7b4d-4e8f-9a6b-1c2d3e4f5a6b',
            name: 'Sales',
            status: DimensionStatus::Active,
            inUse: true,
            behaviour: DimensionBehaviour::Normal,
            touched: 0,
            modified: new DateTimeImmutable('2025-03-14'),
        );

        self::assertEquals(CostCenterInput::create('100', 'Sales'), $costCenter->edit());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'lowercase code' => ['sales', 'Sales', '"sales" is not a code'];
        yield 'empty code' => ['', 'Sales', '"" is not a code'];
        yield 'code too long' => [str_repeat('1', 17), 'Sales', 'is not a code'];
        yield 'empty name' => ['100', '', 'Cost center name must not be empty.'];
        yield 'name too long' => ['100', str_repeat('a', 81), 'Cost center name must be at most 80 characters, 81 given'];
    }

    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInput(string $code, string $name, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        CostCenterInput::create($code, $name);
    }

    public function testRejectsAnInvalidNameChange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CostCenterInput::create('100', 'Sales')->name('');
    }

    public function testReadModelRejectsEmptyRequiredValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cost center uid must not be empty.');

        new CostCenter(TestTokens::company('1000'), '100', '', 'Sales', DimensionStatus::Active, false, DimensionBehaviour::Normal, 0, null);
    }
}
