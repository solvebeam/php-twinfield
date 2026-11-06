<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ProcessXml;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

final class XmlValueTest extends TestCase
{
    public function testDates(): void
    {
        self::assertSame('20250131', XmlValue::date(new DateTimeImmutable('2025-01-31 13:45')));
        self::assertEquals(new DateTimeImmutable('2025-01-31 00:00'), XmlValue::toDate('20250131'));
    }

    public function testMoney(): void
    {
        self::assertSame('12.00', XmlValue::money(12));
        self::assertSame('-12.00', XmlValue::money(-12));
        self::assertSame('12.50', XmlValue::money('12.5'));
        self::assertSame('0.05', XmlValue::money('0.05'));
        self::assertSame('1234567890123.00', XmlValue::money('1234567890123'));
    }

    public function testPeriods(): void
    {
        self::assertSame('2025/01', XmlValue::period(2025, 1));
        self::assertSame('2025/55', XmlValue::period(2025, 55));
        self::assertSame(['year' => 2025, 'number' => 6], XmlValue::toPeriod('2025/6'));
        self::assertSame(['year' => 2025, 'number' => 12], XmlValue::toPeriod('2025/12'));
    }

    public function testBooleans(): void
    {
        self::assertSame('true', XmlValue::boolean(true));
        self::assertSame('false', XmlValue::boolean(false));
        self::assertTrue(XmlValue::toBoolean('true'));
        self::assertFalse(XmlValue::toBoolean('false'));
    }

    public function testCodes(): void
    {
        self::assertSame('NLA000001', XmlValue::code('NLA000001'));
        self::assertSame('A_B-C+1', XmlValue::code('A_B-C+1'));
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidValues(): iterable
    {
        yield 'date with dashes' => [static fn () => XmlValue::toDate('2025-01-31')];
        yield 'date that does not exist' => [static fn () => XmlValue::toDate('20250230')];
        yield 'money with comma' => [static fn () => XmlValue::money('12,50')];
        yield 'money with 3 decimals' => [static fn () => XmlValue::money('12.505')];
        yield 'period 100' => [static fn () => XmlValue::period(2025, 100)];
        yield 'period without slash' => [static fn () => XmlValue::toPeriod('202501')];
        yield 'boolean 1' => [static fn () => XmlValue::toBoolean('1')];
        yield 'code with lower case' => [static fn () => XmlValue::code('abc')];
        yield 'code of 17 characters' => [static fn () => XmlValue::code(str_repeat('A', 17))];
        yield 'empty code' => [static fn () => XmlValue::code('')];
    }

    /**
     * @param callable(): mixed $call
     */
    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(callable $call): void
    {
        $this->expectException(InvalidArgumentException::class);

        $call();
    }
}
