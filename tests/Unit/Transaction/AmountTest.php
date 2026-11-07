<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Transaction;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Transaction\Amount;

final class AmountTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validAmounts(): iterable
    {
        yield 'integer' => ['121', '121.00'];
        yield 'one decimal' => ['12.5', '12.50'];
        yield 'two decimals' => ['0.01', '0.01'];
        yield 'negative' => ['-12.10', '-12.10'];
        yield 'negative zero' => ['-0.00', '0.00'];
        yield 'leading zeros' => ['007.5', '7.50'];
        yield 'large' => ['123456789012345.67', '123456789012345.67'];
    }

    #[DataProvider('validAmounts')]
    public function testNormalisesToTwoDecimals(string $input, string $expected): void
    {
        /** @var numeric-string $input */
        self::assertSame($expected, Amount::of($input)->value);
        self::assertSame($expected, (string) Amount::of($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'comma' => ['1,50'];
        yield 'three decimals' => ['0.125'];
        yield 'exponent' => ['1e3'];
        yield 'empty' => [''];
        yield 'plus sign' => ['+1.00'];
        yield 'spaces' => [' 1.00'];
        yield 'text' => ['ten'];
        yield 'trailing dot' => ['1.'];
    }

    #[DataProvider('invalidAmounts')]
    public function testRefusesInvalidAmounts(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var numeric-string $input */
        Amount::of($input);
    }

    public function testCalculatesExactly(): void
    {
        // 0.1 + 0.2 is not 0.3 with floats.
        self::assertSame('0.30', Amount::of('0.1')->plus('0.2')->value);
        self::assertSame('-0.10', Amount::of('0.1')->minus('0.2')->value);
        self::assertSame('-121.00', Amount::of('121')->negate()->value);
        self::assertSame('0.00', Amount::zero()->negate()->value);
        self::assertSame('12.10', Amount::of('-12.1')->absolute()->value);
        self::assertSame('100.00', Amount::sum(['10', Amount::of('90'), '0.00'])->value);
        self::assertSame('0.00', Amount::sum([])->value);
    }

    public function testCompares(): void
    {
        self::assertTrue(Amount::of('1.5')->equals('1.50'));
        self::assertSame(-1, Amount::of('1')->compare('2'));
        self::assertSame(1, Amount::of('2')->compare(Amount::of('1')));
        self::assertTrue(Amount::zero()->isZero());
        self::assertTrue(Amount::of('-0.01')->isNegative());
        self::assertTrue(Amount::of('0.01')->isPositive());
        self::assertFalse(Amount::zero()->isPositive());
    }

    public function testFromStringValidatesExternalInput(): void
    {
        self::assertSame('12.50', Amount::fromString('12.5')->value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"12,50" is not an amount');

        Amount::fromString('12,50');
    }

    public function testOfKeepsAnAmount(): void
    {
        $amount = Amount::of('1');

        self::assertSame($amount, Amount::of($amount));
        self::assertNull(Amount::ofNullable(null));
    }
}
