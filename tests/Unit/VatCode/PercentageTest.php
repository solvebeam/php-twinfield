<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\VatCode;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\VatCode\Percentage;

final class PercentageTest extends TestCase
{
    /**
     * @return iterable<string, array{numeric-string, string}>
     */
    public static function percentages(): iterable
    {
        yield 'integer' => ['21', '21'];
        yield 'nine decimals from Twinfield' => ['21.000000000', '21'];
        yield 'one decimal from Twinfield' => ['100.0', '100'];
        yield 'decimal' => ['5.50', '5.5'];
        yield 'zero' => ['0.000000000', '0'];
        yield 'negative zero' => ['-0.0', '0'];
        yield 'negative share' => ['-100.0', '-100'];
        yield 'small' => ['0.000000001', '0.000000001'];
        yield 'ten' => ['10', '10'];
    }

    /**
     * @param numeric-string $input
     */
    #[DataProvider('percentages')]
    public function testNormalises(string $input, string $expected): void
    {
        self::assertSame($expected, Percentage::of($input)->value);
        self::assertSame($expected, (string) Percentage::fromString($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'comma' => ['5,5'];
        yield 'percent sign' => ['21%'];
        yield 'exponent' => ['1e2'];
        yield 'ten decimals' => ['0.0000000001'];
        yield 'empty' => [''];
        yield 'plus sign' => ['+21'];
    }

    #[DataProvider('invalid')]
    public function testRefusesInvalidInput(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a percentage');

        Percentage::fromString($input);
    }

    public function testCompares(): void
    {
        $percentage = Percentage::of('21');

        self::assertTrue($percentage->equals('21.000'));
        self::assertFalse($percentage->equals('9'));
        self::assertSame(1, $percentage->compare('9'));
        self::assertSame(0, $percentage->compare(Percentage::of('21.0')));
        self::assertSame(-1, $percentage->compare('100'));
        self::assertTrue(Percentage::of('0.0')->isZero());
        self::assertTrue(Percentage::of('-100')->isNegative());
        self::assertFalse(Percentage::of('0')->isNegative());
        self::assertSame($percentage, Percentage::of($percentage));
    }

    /**
     * @return iterable<string, array{numeric-string, numeric-string, string}>
     */
    public static function applications(): iterable
    {
        yield '21% of 100' => ['21', '100.00', '21.00'];
        yield 'rounds half up' => ['21', '10.05', '2.11'];
        yield 'rounds down' => ['21', '10.02', '2.10'];
        yield 'half a cent exactly' => ['9', '0.50', '0.05'];
        yield 'negative amount rounds away from zero' => ['21', '-10.05', '-2.11'];
        yield 'zero percent' => ['0', '123.45', '0.00'];
        yield 'decimal percentage' => ['5.5', '19.99', '1.10'];
        yield 'negative share' => ['-100', '21.00', '-21.00'];
        yield 'large amount stays exact' => ['21', '99999999999.99', '21000000000.00'];
    }

    /**
     * @param numeric-string $percentage
     * @param numeric-string $amount
     */
    #[DataProvider('applications')]
    public function testAppliesToAnAmount(string $percentage, string $amount, string $expected): void
    {
        self::assertSame($expected, Percentage::of($percentage)->applyTo($amount)->value);
        self::assertSame($expected, Percentage::of($percentage)->applyTo(Amount::of($amount))->value);
    }
}
