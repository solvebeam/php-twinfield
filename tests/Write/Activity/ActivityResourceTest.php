<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Activity;

use DateTimeImmutable;
use SolveBeam\Twinfield\Activity\ActivityInput;
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Dimension\Quantity;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates, updates, deletes and reactivates a temporary activity A9xx; always deletes it again.
 *
 * Uses codes in the format A[0-9][0-9][0-9], the default code format of activities.
 */
final class ActivityResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $activities = $this->twinfield->activities();
        $code = $this->unusedCode();

        try {
            $created = $activities->create(
                ActivityInput::create($code, 'SDK test activity')
                    ->shortName('SDK test')
                    ->invoiceDescription('Created by the twinfield-php write tests')
                    ->validFrom(new DateTimeImmutable('2025-01-01'))
                    ->billable(true, ChangeMode::Allow, forRatio: true)
                    ->quantity(Quantity::create('KM')->billable(false, locked: true)),
            );

            self::assertSame($code, $created->code);
            self::assertSame(DimensionStatus::Active, $created->status);

            $read = $activities->get($code);

            self::assertSame('SDK test activity', $read->name);
            self::assertSame('SDK test', $read->shortName);
            self::assertSame('Created by the twinfield-php write tests', $read->projects->invoiceDescription);
            self::assertEquals(new DateTimeImmutable('2025-01-01'), $read->projects->validFrom);
            self::assertNull($read->projects->validTill);
            self::assertTrue($read->projects->billable->billable);
            self::assertTrue($read->projects->billable->forRatio);
            self::assertSame(ChangeMode::Allow, $read->projects->billable->change);
            self::assertCount(1, $read->projects->quantities);
            self::assertSame('KM', $read->projects->quantities[0]->label);
            self::assertTrue($read->projects->quantities[0]->billableLocked);

            try {
                $activities->create(ActivityInput::create($code, 'Duplicate'));
                self::fail('Expected a DimensionAlreadyExistsException.');
            } catch (DimensionAlreadyExistsException) {
                self::assertSame('SDK test activity', $activities->get($code)->name, 'The existing activity is unchanged.');
            }

            $activities->update(
                $read->edit()
                    ->name('SDK test activity (updated)')
                    ->shortName(null)
                    ->validTill(new DateTimeImmutable('2025-12-31'))
                    ->quantity(Quantity::create('Parking')->mandatory()),
            );

            $updated = $activities->get($code);

            self::assertSame('SDK test activity (updated)', $updated->name);
            self::assertNull($updated->shortName, 'An update clears values that were removed.');
            self::assertEquals(new DateTimeImmutable('2025-12-31'), $updated->projects->validTill);
            self::assertSame(['KM', 'Parking'], array_map(static fn (Quantity $q): string => $q->label, $updated->projects->quantities));
            self::assertSame('Created by the twinfield-php write tests', $updated->projects->invoiceDescription, 'edit() keeps other values.');

            self::assertSame(DeleteResult::Deleted, $activities->delete($code));
            self::assertSame(DimensionStatus::Deleted, $activities->get($code)->status);

            $activities->activate($code);
            self::assertSame(DimensionStatus::Active, $activities->get($code)->status);
        } finally {
            if ($activities->find($code)?->status === DimensionStatus::Active) {
                $activities->delete($code);
            }
        }
    }

    private function unusedCode(): string
    {
        for ($number = 999; $number > 900; $number--) {
            $code = sprintf('A%03d', $number);

            if ($this->twinfield->activities()->find($code) === null) {
                return $code;
            }
        }

        self::fail('No unused activity code A901–A999.');
    }
}
