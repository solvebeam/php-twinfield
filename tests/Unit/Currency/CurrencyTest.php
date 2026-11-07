<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Currency;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Currency\Currency;
use SolveBeam\Twinfield\Currency\CurrencyRate;
use SolveBeam\Twinfield\Currency\CurrencyStatus;
use SolveBeam\Twinfield\Currency\ExchangeRate;

final class CurrencyTest extends TestCase
{
    public function testSortsRatesByStartDate(): void
    {
        $currency = $this->usd(['2026-07-01' => '1.0912', '2025-01-01' => '1.1', '2026-01-01' => '1.085']);

        self::assertSame(
            ['2025-01-01', '2026-01-01', '2026-07-01'],
            array_map(static fn (CurrencyRate $rate): string => $rate->startDate->format('Y-m-d'), $currency->rates),
        );
    }

    public function testFindsTheRateOnADate(): void
    {
        $currency = $this->usd(['2026-07-01' => '1.0912', '2026-01-01' => '1.085']);

        self::assertNull($currency->rateOn(new DateTimeImmutable('2025-12-31')));
        self::assertSame('1.085', $currency->rateOn(new DateTimeImmutable('2026-01-01'))?->rate->value);
        self::assertSame('1.085', $currency->rateOn(new DateTimeImmutable('2026-06-30 23:59'))?->rate->value);
        self::assertSame('1.0912', $currency->rateOn(new DateTimeImmutable('2026-07-01 00:00'))?->rate->value);
        self::assertSame('1.0912', $currency->rateOn(new DateTimeImmutable('2030-01-01'))?->rate->value);
    }

    public function testLatestRate(): void
    {
        self::assertSame('1.0912', $this->usd(['2026-07-01' => '1.0912', '2026-01-01' => '1.085'])->latestRate()?->rate->value);
        self::assertNull($this->usd([])->latestRate());
        self::assertNull($this->usd([])->rateOn(new DateTimeImmutable('2026-01-01')));
    }

    public function testEditKeepsAllValuesAndRates(): void
    {
        $input = $this->usd(['2026-07-01' => '1.0912', '2026-01-01' => '1.085'])->edit();

        self::assertSame('USD', $input->code);
        self::assertSame('US dollar', $input->name);
        self::assertSame('$', $input->shortName);
        self::assertEquals($this->usd(['2026-01-01' => '1.085', '2026-07-01' => '1.0912'])->rates, $input->rates());
        self::assertSame([], $input->removedRates());
    }

    public function testRejectsTwoRatesOnTheSameStartDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency USD has more than one rate on 2026-01-01.');

        new Currency('1000', 'USD', 'US dollar', null, CurrencyStatus::Active, [
            new CurrencyRate(new DateTimeImmutable('2026-01-01'), ExchangeRate::of('1.085')),
            new CurrencyRate(new DateTimeImmutable('2026-01-01'), ExchangeRate::of('1.09')),
        ]);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Currency('1000', 'USD', '', null, CurrencyStatus::Active, []);
    }

    /**
     * @param array<string, numeric-string> $rates Rate by start date `Y-m-d`.
     */
    private function usd(array $rates): Currency
    {
        $list = [];

        foreach ($rates as $startDate => $rate) {
            $list[] = new CurrencyRate(new DateTimeImmutable($startDate), ExchangeRate::of($rate));
        }

        return new Currency('1000', 'USD', 'US dollar', '$', CurrencyStatus::Active, $list);
    }
}
