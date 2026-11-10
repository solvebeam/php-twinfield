<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Currency;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Currency\CurrencyInput;
use SolveBeam\Twinfield\Currency\CurrencyRate;
use SolveBeam\Twinfield\Currency\ExchangeRate;

final class CurrencyInputTest extends TestCase
{
    public function testBuildsTheMinimalRequest(): void
    {
        self::assertSame(
            '<currency><office>1000</office><code>USD</code><name>US dollar</name><shortname/></currency>',
            CurrencyInput::create('USD', 'US dollar')->toXml('1000')->toXml(),
        );
    }

    public function testBuildsTheRequestWithRatesSortedByStartDate(): void
    {
        $input = CurrencyInput::create('USD', 'US dollar')
            ->shortName('$')
            ->rate(new DateTimeImmutable('2026-07-01'), '1.0912')
            ->rate(new DateTimeImmutable('2026-01-01'), ExchangeRate::of('1.0850'));

        self::assertSame(
            '<currency><office>1000</office><code>USD</code><name>US dollar</name><shortname>$</shortname>'
            . '<rates>'
            . '<rate><startdate>20260101</startdate><rate>1.085</rate></rate>'
            . '<rate><startdate>20260701</startdate><rate>1.0912</rate></rate>'
            . '</rates></currency>',
            $input->toXml('1000')->toXml(),
        );
    }

    public function testReplacesTheRateOnTheSameStartDate(): void
    {
        $input = CurrencyInput::create('USD', 'US dollar')
            ->rate(new DateTimeImmutable('2026-01-01'), '1.0850')
            ->rate(new DateTimeImmutable('2026-01-01 15:30'), '1.0900');

        self::assertEquals(
            [new CurrencyRate(new DateTimeImmutable('2026-01-01'), ExchangeRate::of('1.09'))],
            $input->rates(),
        );
    }

    public function testRemovesARateWithItsValueWhenKnown(): void
    {
        $input = CurrencyInput::create('USD', 'US dollar')
            ->rate(new DateTimeImmutable('2026-01-01'), '1.0850')
            ->rate(new DateTimeImmutable('2026-07-01'), '1.0912')
            ->removeRate(new DateTimeImmutable('2026-01-01'))
            ->removeRate(new DateTimeImmutable('2025-01-01'));

        self::assertSame(
            '<currency><office>1000</office><code>USD</code><name>US dollar</name><shortname/>'
            . '<rates>'
            . '<rate><startdate>20260701</startdate><rate>1.0912</rate></rate>'
            . '<rate status="deleted"><startdate>20250101</startdate></rate>'
            . '<rate status="deleted"><startdate>20260101</startdate><rate>1.085</rate></rate>'
            . '</rates></currency>',
            $input->toXml('1000')->toXml(),
        );
        self::assertEquals(
            [new DateTimeImmutable('2025-01-01'), new DateTimeImmutable('2026-01-01')],
            $input->removedRates(),
        );
    }

    public function testSettingARateAgainUndoesTheRemoval(): void
    {
        $input = CurrencyInput::create('USD', 'US dollar')
            ->removeRate(new DateTimeImmutable('2026-01-01'))
            ->rate(new DateTimeImmutable('2026-01-01'), '1.0850');

        self::assertSame([], $input->removedRates());
        self::assertCount(1, $input->rates());
    }

    public function testIsImmutable(): void
    {
        $input = CurrencyInput::create('USD', 'US dollar');

        $input->name('Dollar')->shortName('$')->rate(new DateTimeImmutable('2026-01-01'), '1.085');

        self::assertSame('US dollar', $input->name);
        self::assertNull($input->shortName);
        self::assertSame([], $input->rates());
    }

    public function testEmptyShortNameClearsIt(): void
    {
        self::assertNull(CurrencyInput::create('USD', 'US dollar')->shortName('$')->shortName('')->shortName);
    }

    public function testEscapesValues(): void
    {
        self::assertStringContainsString(
            '<name>Dollar &amp; cents</name><shortname>&lt;$&gt;</shortname>',
            CurrencyInput::create('USD', 'Dollar & cents')->shortName('<$>')->toXml('1000')->toXml(),
        );
    }

    public function testRejectsAnInvalidCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CurrencyInput::create('usd', 'US dollar');
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CurrencyInput::create('USD', 'US dollar')->name('');
    }

    public function testRejectsAnInvalidRate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (deliberately invalid input, for callers without static analysis)
        CurrencyInput::create('USD', 'US dollar')->rate(new DateTimeImmutable('2026-01-01'), '1,085');
    }
}
