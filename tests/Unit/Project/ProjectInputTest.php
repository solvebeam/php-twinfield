<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Project;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Project\ProjectInput;
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\CodeSetting;
use SolveBeam\Twinfield\Dimension\Quantity;

final class ProjectInputTest extends TestCase
{
    public function testIsImmutable(): void
    {
        $base = ProjectInput::create('P0001', 'Consultancy');
        $changed = $base->name('Development')->customer('1010')->quantity(Quantity::create('KM'));

        self::assertSame('Consultancy', $base->name);
        self::assertEquals(CodeSetting::inherit(), $base->projects->customer);
        self::assertSame([], $base->projects->quantities);

        self::assertSame('Development', $changed->name);
        self::assertEquals(new CodeSetting('1010', ChangeMode::Allow), $changed->projects->customer);
        self::assertCount(1, $changed->projects->quantities);
    }

    public function testKeepsOtherSettingsWhenChangingOne(): void
    {
        $input = ProjectInput::create('P0001', 'Consultancy')
            ->validFrom(new DateTimeImmutable('2025-01-01'))
            ->invoiceDescription('Advice')
            ->rate('T001', ChangeMode::Disallow)
            ->quantity(Quantity::create('KM'))
            ->quantity(Quantity::create('Parking'))
            ->customer('1010');

        self::assertEquals(new DateTimeImmutable('2025-01-01'), $input->projects->validFrom);
        self::assertSame('Advice', $input->projects->invoiceDescription);
        self::assertEquals(new CodeSetting('T001', ChangeMode::Disallow), $input->projects->rate);
        self::assertSame(
            ['KM', 'Parking'],
            array_map(static fn (Quantity $q): string => $q->label, $input->projects->quantities),
        );
    }

    public function testClearsValues(): void
    {
        $input = ProjectInput::create('P0001', 'Consultancy')
            ->shortName('Cons')
            ->vatCode('VH')
            ->invoiceDescription('Advice')
            ->validTill(new DateTimeImmutable('2025-12-31'))
            ->quantity(Quantity::create('KM'));

        $cleared = $input->shortName(null)->vatCode('')->invoiceDescription(null)->validTill(null)->quantities();

        self::assertNull($cleared->shortName);
        self::assertNull($cleared->vatCode);
        self::assertNull($cleared->projects->invoiceDescription);
        self::assertNull($cleared->projects->validTill);
        self::assertSame([], $cleared->projects->quantities);
    }

    public function testAcceptsANameOf80Characters(): void
    {
        $name = str_repeat('x', 80);

        self::assertSame($name, ProjectInput::create('P0001', $name)->name);
        self::assertStringContainsString(
            "<name>{$name}</name>",
            ProjectInput::create('P0001', $name)->toXml('1000')->toXml(),
        );
    }

    public function testStoresDatesWithoutTime(): void
    {
        $input = ProjectInput::create('P0001', 'Consultancy')->validFrom(new DateTimeImmutable('2025-01-01 13:45:10'));

        self::assertEquals(new DateTimeImmutable('2025-01-01 00:00:00'), $input->projects->validFrom);
    }

    public function testBuildsQuantities(): void
    {
        self::assertEquals(
            new Quantity('KM', 'KM', billable: true, billableLocked: true, mandatory: true),
            Quantity::create('KM')->rate('KM')->billable(true, locked: true)->mandatory(),
        );
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'empty code' => [static fn (): mixed => ProjectInput::create('', 'Name'), '"" is not a code'];
        yield 'lowercase code' => [
            static fn (): mixed => ProjectInput::create('p0001', 'Name'),
            '"p0001" is not a code',
        ];
        yield 'empty name' => [
            static fn (): mixed => ProjectInput::create('P0001', ''),
            'Project name must not be empty.',
        ];
        yield 'long name' => [
            static fn (): mixed => ProjectInput::create('P0001', str_repeat('x', 81)),
            'Project name must be at most 80 characters, 81 given',
        ];
        yield 'long short name' => [
            static fn (): mixed => ProjectInput::create('P0001', 'Name')->shortName(str_repeat('x', 21)),
            'Project short name must be at most 20 characters',
        ];
        yield 'long VAT code' => [
            static fn (): mixed => ProjectInput::create('P0001', 'Name')->vatCode(str_repeat('X', 17)),
            'is not a code: 1-16 times A-Z, 0-9, _, - or +',
        ];
        yield 'long invoice description' => [
            static fn (): mixed => ProjectInput::create('P0001', 'Name')->invoiceDescription(str_repeat('x', 129)),
            'Invoice description must be at most 128 characters',
        ];
        yield 'invalid customer code' => [
            static fn (): mixed => ProjectInput::create('P0001', 'Name')->customer('10 10'),
            '"10 10" is not a code',
        ];
        yield 'empty quantity label' => [
            static fn (): mixed => Quantity::create(''),
            'Quantity label must not be empty.',
        ];
        yield 'long quantity label' => [
            static fn (): mixed => Quantity::create(str_repeat('x', 37)),
            'Quantity label must be at most 36 characters',
        ];
    }

    /**
     * @param callable(): mixed $create
     */
    #[DataProvider('invalidInput')]
    public function testRejectsInvalidInput(callable $create, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $create();
    }
}
