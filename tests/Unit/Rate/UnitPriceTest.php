<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Rate;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Rate\UnitPrice;

final class UnitPriceTest extends TestCase
{
    /**
     * @return iterable<string, array{numeric-string, string}>
     */
    public static function validPrices(): iterable
    {
        yield 'integer' => ['95', '95.0000'];
        yield 'two decimals' => ['0.15', '0.1500'];
        yield 'four decimals' => ['0.1550', '0.1550'];
        yield 'zero' => ['0', '0.0000'];
        yield 'leading zeros' => ['007.5', '7.5000'];
    }

    /**
     * @param numeric-string $price
     */
    #[DataProvider('validPrices')]
    public function testNormalisesToFourDecimals(string $price, string $value): void
    {
        self::assertSame($value, UnitPrice::of($price)->value);
        self::assertSame($value, (string) UnitPrice::fromString($price));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPrices(): iterable
    {
        yield 'comma' => ['0,15'];
        yield 'exponent' => ['1e3'];
        yield 'negative' => ['-1'];
        yield 'five decimals' => ['0.12345'];
        yield 'empty' => [''];
        yield 'text' => ['abc'];
    }

    #[DataProvider('invalidPrices')]
    public function testRefusesInvalidPrices(string $price): void
    {
        $this->expectException(InvalidArgumentException::class);

        UnitPrice::fromString($price);
    }

    public function testComparesValues(): void
    {
        $price = UnitPrice::of('0.15');

        self::assertSame($price, UnitPrice::of($price));
        self::assertTrue($price->equals('0.1500'));
        self::assertFalse($price->equals('0.151'));
        self::assertTrue(UnitPrice::of('0.0000')->isZero());
        self::assertFalse($price->isZero());
    }
}
