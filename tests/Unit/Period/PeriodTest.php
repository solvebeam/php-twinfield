<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Period;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Period\Period;

final class PeriodTest extends TestCase
{
    public function testFormatsAsTwinfieldWritesIt(): void
    {
        self::assertSame('2026/03', (string) Period::of(2026, 3));
        self::assertSame('2026/55', (string) Period::of(2026, 55));
    }

    public function testParsesOneOrTwoDigits(): void
    {
        $period = Period::fromString('2026/3');

        self::assertSame(2026, $period->year);
        self::assertSame(3, $period->number);
        self::assertTrue($period->equals(Period::fromString('2026/03')));
    }

    public function testEquals(): void
    {
        self::assertTrue(Period::of(2026, 0)->equals(Period::of(2026, 0)));
        self::assertFalse(Period::of(2026, 1)->equals(Period::of(2025, 1)));
        self::assertFalse(Period::of(2026, 1)->equals(Period::of(2026, 2)));
    }

    public function testRefusesANegativeNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Period::of(2026, -1);
    }

    public function testRefusesAnotherFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Period::fromString('2026-03');
    }
}
