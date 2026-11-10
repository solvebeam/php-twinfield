<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Activity;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Activity\ActivityInput;
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\CodeSetting;
use SolveBeam\Twinfield\Dimension\Quantity;

final class ActivityInputTest extends TestCase
{
    public function testIsImmutable(): void
    {
        $base = ActivityInput::create('A001', 'Consultancy');
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
        $input = ActivityInput::create('A001', 'Consultancy')
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
        $input = ActivityInput::create('A001', 'Consultancy')
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

    public function testStoresDatesWithoutTime(): void
    {
        $input = ActivityInput::create('A001', 'Consultancy')->validFrom(new DateTimeImmutable('2025-01-01 13:45:10'));

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
        yield 'empty code' => [static fn (): mixed => ActivityInput::create('', 'Name'), '"" is not a code'];
        yield 'lowercase code' => [
            static fn (): mixed => ActivityInput::create('a001', 'Name'),
            '"a001" is not a code',
        ];
        yield 'empty name' => [
            static fn (): mixed => ActivityInput::create('A001', ''),
            'Activity name must not be empty.',
        ];
        yield 'long name' => [
            static fn (): mixed => ActivityInput::create('A001', str_repeat('x', 41)),
            'Activity name must be at most 40 characters, 41 given',
        ];
        yield 'long short name' => [
            static fn (): mixed => ActivityInput::create('A001', 'Name')->shortName(str_repeat('x', 21)),
            'Activity short name must be at most 20 characters',
        ];
        yield 'long VAT code' => [
            static fn (): mixed => ActivityInput::create('A001', 'Name')->vatCode(str_repeat('X', 17)),
            'is not a code: 1-16 times A-Z, 0-9, _, - or +',
        ];
        yield 'long invoice description' => [
            static fn (): mixed => ActivityInput::create('A001', 'Name')->invoiceDescription(str_repeat('x', 129)),
            'Invoice description must be at most 128 characters',
        ];
        yield 'invalid customer code' => [
            static fn (): mixed => ActivityInput::create('A001', 'Name')->customer('10 10'),
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
