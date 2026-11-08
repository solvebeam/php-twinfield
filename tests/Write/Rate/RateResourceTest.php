<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Rate;

use DateTimeImmutable;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Rate\RateAlreadyExistsException;
use SolveBeam\Twinfield\Rate\RateInput;
use SolveBeam\Twinfield\Rate\RateStatus;
use SolveBeam\Twinfield\Rate\RateType;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates, updates, deletes and reactivates a temporary rate SDKTEST9xx; always deletes it again.
 */
final class RateResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $rates = $this->twinfield->rates();
        $code = $this->unusedCode();

        try {
            $created = $rates->create(
                RateInput::quantity($code, 'SDK test rate')
                    ->shortName('SDK test')
                    ->currency('EUR')
                    ->price(new DateTimeImmutable('2025-01-01'), internal: '0.15', external: '0.23'),
            );

            self::assertSame($code, $created->code);
            self::assertSame(RateStatus::Active, $created->status);
            self::assertFalse($created->inUse);
            self::assertSame(RateType::Quantity, $created->type);
            self::assertSame(1, $created->unit);
            self::assertSame('EUR', $created->currency);
            self::assertSame('SDK test', $created->shortName);
            self::assertCount(1, $created->prices);
            self::assertNull($created->prices[0]->until, 'Sent as 29991231, read as no end date.');
            self::assertSame('0.2300', $created->prices[0]->external->value);

            try {
                $rates->create(
                    RateInput::quantity($code, 'Duplicate')->price(new DateTimeImmutable('2025-01-01'), '1', '1'),
                );
                self::fail('Expected a RateAlreadyExistsException.');
            } catch (RateAlreadyExistsException) {
                self::assertSame('SDK test rate', $rates->get($code)->name, 'The existing rate is unchanged.');
            }

            $updated = $rates->update(
                $created->edit()
                    ->name('SDK test rate (updated)')
                    ->shortName(null)
                    ->newPriceFrom(new DateTimeImmutable('2026-01-01'), internal: '0.16', external: '0.25'),
            );

            self::assertSame('SDK test rate (updated)', $updated->name);
            self::assertNull($updated->shortName, 'An update clears values that were removed.');
            self::assertCount(2, $updated->prices, 'The existing line is changed, not added again.');
            self::assertSame($created->prices[0]->id, $updated->prices[0]->id);
            self::assertEquals(new DateTimeImmutable('2025-12-31'), $updated->prices[0]->until);
            self::assertSame('0.2500', $updated->priceOn(new DateTimeImmutable('2026-03-01'))?->external->value);

            $removed = $rates->update($updated->edit()->removePrice(new DateTimeImmutable('2026-01-01')));

            self::assertCount(1, $removed->prices, 'A line with status deleted is removed.');

            self::assertSame(DeleteResult::Deleted, $rates->delete($code));
            self::assertSame(RateStatus::Deleted, $rates->get($code)->status);

            $rates->activate($code);
            self::assertSame(RateStatus::Active, $rates->get($code)->status);
        } finally {
            if ($rates->find($code)?->status === RateStatus::Active) {
                $rates->delete($code);
            }
        }
    }

    private function unusedCode(): string
    {
        for ($number = 999; $number > 900; $number--) {
            $code = sprintf('SDKTEST%03d', $number);

            if ($this->twinfield->rates()->find($code) === null) {
                return $code;
            }
        }

        self::fail('No unused rate code SDKTEST901–SDKTEST999.');
    }
}
