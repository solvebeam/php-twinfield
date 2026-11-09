<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Currency;

use DateTimeImmutable;
use SolveBeam\Twinfield\Currency\CurrencyAlreadyExistsException;
use SolveBeam\Twinfield\Currency\CurrencyInput;
use SolveBeam\Twinfield\Currency\CurrencyRate;
use SolveBeam\Twinfield\Currency\CurrencyStatus;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Reads every currency, and creates, updates and deletes a temporary currency (ZZZ or the
 * like); always deletes it again.
 *
 * Also checks the Twinfield behaviour the SDK relies on, which its documentation leaves
 * open: a `<currency>` with only office and code reads it, and rates are kept by start date.
 */
final class CurrencyResourceTest extends WriteTestCase
{
    public function testReadsEveryCurrency(): void
    {
        $currencies = $this->twinfield->currencies();
        $count = 0;

        foreach ($currencies->search()->all() as $summary) {
            $currency = $currencies->get($summary->code);

            self::assertSame($summary->code, $currency->code);
            self::assertSame($summary->name, $currency->name);
            self::assertSame($this->company(), $currency->company->code);
            $count++;
        }

        self::assertGreaterThan(0, $count);
    }

    public function testLifecycle(): void
    {
        $currencies = $this->twinfield->currencies();
        $code = Example::unusedCurrencyCode($this->twinfield);

        try {
            $created = $currencies->create(
                CurrencyInput::create($code, 'SDK test currency')
                    ->shortName('Z')
                    ->rate(new DateTimeImmutable('2025-01-01'), '1.1')
                    ->rate(new DateTimeImmutable('2026-01-01'), '1.123456'),
            );

            self::assertSame($code, $created->code);
            self::assertSame(CurrencyStatus::Active, $created->status);
            self::assertSame('Z', $created->shortName);
            self::assertSame(['2025-01-01' => '1.1', '2026-01-01' => '1.123456'], $this->rates($created->rates));

            try {
                $currencies->create(CurrencyInput::create($code, 'Duplicate'));
                self::fail('Expected a CurrencyAlreadyExistsException.');
            } catch (CurrencyAlreadyExistsException) {
                self::assertSame('SDK test currency', $currencies->get($code)->name, 'The existing one is unchanged.');
            }

            // Add a rate, change one and remove one.
            $updated = $currencies->update(
                $created->edit()
                    ->name('SDK test currency & more')
                    ->rate(new DateTimeImmutable('2026-07-01'), '1.2')
                    ->rate(new DateTimeImmutable('2025-01-01'), '1.05')
                    ->removeRate(new DateTimeImmutable('2026-01-01')),
            );

            self::assertSame('SDK test currency & more', $updated->name);
            self::assertSame(['2025-01-01' => '1.05', '2026-07-01' => '1.2'], $this->rates($updated->rates));

            // An update without rates must keep them; the SDK only relies on this for a rename.
            $renamed = $currencies->save(CurrencyInput::create($code, 'SDK test currency')->shortName('Z'));

            self::assertSame('SDK test currency', $renamed->name);
            self::assertSame(['2025-01-01' => '1.05', '2026-07-01' => '1.2'], $this->rates($renamed->rates));

            $currencies->delete($code);
            self::assertFalse($currencies->exists($code), 'A deleted currency is not in the Finder.');
        } finally {
            if ($currencies->exists($code)) {
                $currencies->delete($code);
            }
        }
    }

    /**
     * @param list<CurrencyRate> $rates
     *
     * @return array<string, string> Rate by start date `Y-m-d`.
     */
    private function rates(array $rates): array
    {
        $values = [];

        foreach ($rates as $rate) {
            $values[$rate->startDate->format('Y-m-d')] = $rate->rate->value;
        }

        return $values;
    }
}
