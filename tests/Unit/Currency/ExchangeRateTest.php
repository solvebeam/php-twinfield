<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Currency;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Currency\ExchangeRate;

final class ExchangeRateTest extends TestCase
{
    /**
     * @return iterable<string, array{numeric-string, string}>
     */
    public static function rates(): iterable
    {
        yield 'decimals' => ['0.8282', '0.8282'];
        yield 'trailing zeros' => ['1.0850000000', '1.085'];
        yield 'integer' => ['12', '12'];
        yield 'integer with zero decimals' => ['12.000', '12'];
        yield 'leading zeros' => ['007.50', '7.5'];
        yield 'many decimals' => ['0.000123456789', '0.000123456789'];
    }

    /**
     * @param numeric-string $rate
     */
    #[DataProvider('rates')]
    public function testNormalisesRates(string $rate, string $expected): void
    {
        self::assertSame($expected, ExchangeRate::of($rate)->value);
        self::assertSame($expected, (string) ExchangeRate::fromString($rate));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRates(): iterable
    {
        yield 'comma' => ['1,08'];
        yield 'exponent' => ['1e3'];
        yield 'zero' => ['0'];
        yield 'zero with decimals' => ['0.000'];
        yield 'negative' => ['-1.5'];
        yield 'empty' => [''];
        yield 'text' => ['abc'];
        yield 'leading dot' => ['.5'];
    }

    #[DataProvider('invalidRates')]
    public function testRejectsInvalidRates(string $rate): void
    {
        $this->expectException(InvalidArgumentException::class);

        ExchangeRate::fromString($rate);
    }

    public function testOfReturnsTheSameRate(): void
    {
        $rate = ExchangeRate::of('1.085');

        self::assertSame($rate, ExchangeRate::of($rate));
    }

    public function testEquals(): void
    {
        $rate = ExchangeRate::of('1.0850');

        self::assertTrue($rate->equals('1.085'));
        self::assertTrue($rate->equals(ExchangeRate::of('01.08500')));
        self::assertFalse($rate->equals('1.0851'));
    }
}
