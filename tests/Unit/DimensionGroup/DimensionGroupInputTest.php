<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\DimensionGroup;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupInput;

final class DimensionGroupInputTest extends TestCase
{
    public function testIsImmutable(): void
    {
        $base = DimensionGroupInput::create('NL', 'Dutch customers');
        $changed = $base->name('Dutch partners')->shortName('NL')->add('DEB', '1000');

        self::assertSame('Dutch customers', $base->name);
        self::assertNull($base->shortName);
        self::assertSame([], $base->dimensions());

        self::assertSame('NL', $changed->code);
        self::assertSame('Dutch partners', $changed->name);
        self::assertSame('NL', $changed->shortName);
        self::assertSame([['type' => 'DEB', 'code' => '1000']], $changed->dimensions());
    }

    public function testAddsDimensionsOnceInTheOrderAdded(): void
    {
        $input = DimensionGroupInput::create('NL', 'Dutch customers')
            ->add('DEB', '1001', '1000')
            ->add('KPL', '100')
            ->add('DEB', '1001', '1002');

        self::assertSame([
            ['type' => 'DEB', 'code' => '1001'],
            ['type' => 'DEB', 'code' => '1000'],
            ['type' => 'KPL', 'code' => '100'],
            ['type' => 'DEB', 'code' => '1002'],
        ], $input->dimensions());
        self::assertTrue($input->has('KPL', '100'));
        self::assertFalse($input->has('DEB', '100'), 'The type is part of the identity.');
    }

    public function testRemovesDimensions(): void
    {
        $input = DimensionGroupInput::create('NL', 'Dutch customers')
            ->add('DEB', '1000', '1001', '1002')
            ->add('KPL', '1000')
            ->remove('DEB', '1000', '1002', '9999');

        self::assertSame([
            ['type' => 'DEB', 'code' => '1001'],
            ['type' => 'KPL', 'code' => '1000'],
        ], $input->dimensions());
    }

    public function testClearsAllDimensionsOrOneType(): void
    {
        $input = DimensionGroupInput::create('NL', 'Dutch customers')->add('DEB', '1000', '1001')->add('KPL', '100');

        self::assertSame([['type' => 'KPL', 'code' => '100']], $input->clear('DEB')->dimensions());
        self::assertSame([], $input->clear()->dimensions());
    }

    public function testEmptyShortNameClearsIt(): void
    {
        $input = DimensionGroupInput::create('NL', 'Dutch customers')->shortName('NL');

        self::assertNull($input->shortName('')->shortName);
    }

    public function testAcceptsTheMaximumLengths(): void
    {
        $input = DimensionGroupInput::create('NL', str_repeat('é', 40))->shortName(str_repeat('é', 20));

        self::assertSame(40, mb_strlen($input->name));
        self::assertSame(20, mb_strlen((string) $input->shortName));
    }

    public function testBuildsTheRequest(): void
    {
        self::assertSame(
            '<dimensiongroup status="active"><office>1000</office><code>NL</code>'
            . '<name>Dutch customers &amp; partners</name><shortname>NL</shortname><dimensions>'
            . '<dimension><type>DEB</type><code>1000</code></dimension>'
            . '<dimension><type>KPL</type><code>100</code></dimension>'
            . '</dimensions></dimensiongroup>',
            DimensionGroupInput::create('NL', 'Dutch customers & partners')
                ->shortName('NL')
                ->add('DEB', '1000')
                ->add('KPL', '100')
                ->toXml('1000')
                ->toXml(),
        );
    }

    public function testSendsEmptyElementsToClearShortNameAndDimensions(): void
    {
        self::assertSame(
            '<dimensiongroup status="active"><office>1000</office><code>NL</code><name>Dutch customers</name>'
            . '<shortname/><dimensions/></dimensiongroup>',
            DimensionGroupInput::create('NL', 'Dutch customers')->toXml('1000')->toXml(),
        );
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'empty code' => [
            static fn () => DimensionGroupInput::create('', 'Dutch customers'),
            '"" is not a code',
        ];
        yield 'lowercase code' => [
            static fn () => DimensionGroupInput::create('nl', 'Dutch customers'),
            '"nl" is not a code',
        ];
        yield 'empty name' => [
            static fn () => DimensionGroupInput::create('NL', ''),
            'Dimension group name must not be empty.',
        ];
        yield 'empty new name' => [
            static fn () => DimensionGroupInput::create('NL', 'Dutch customers')->name(''),
            'Dimension group name must not be empty.',
        ];
        yield 'name too long' => [
            static fn () => DimensionGroupInput::create('NL', str_repeat('x', 41)),
            'Dimension group name must be at most 40 characters, 41 given',
        ];
        yield 'short name too long' => [
            static fn () => DimensionGroupInput::create('NL', 'Dutch customers')->shortName(str_repeat('x', 21)),
            'Dimension group short name must be at most 20 characters, 21 given',
        ];
        yield 'invalid dimension type' => [
            static fn () => DimensionGroupInput::create('NL', 'Dutch customers')->add('deb', '1000'),
            '"deb" is not a code',
        ];
        yield 'invalid dimension code' => [
            static fn () => DimensionGroupInput::create('NL', 'Dutch customers')->add('DEB', '1000', ''),
            '"" is not a code',
        ];
    }

    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInput(callable $create, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $create();
    }
}
