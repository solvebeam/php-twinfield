<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Project;

use DateTimeImmutable;
use SolveBeam\Twinfield\Project\ProjectInput;
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Dimension\Quantity;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates, updates, deletes and reactivates a temporary project P99xx; always deletes it again.
 *
 * Uses codes in the format P[0-9][0-9][0-9][0-9], the default code format of projects.
 */
final class ProjectResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $projects = $this->twinfield->projects();
        $code = $this->unusedCode();

        try {
            $created = $projects->create(
                ProjectInput::create($code, 'SDK test project')
                    ->shortName('SDK test')
                    ->invoiceDescription('Created by the twinfield-php write tests')
                    ->validFrom(new DateTimeImmutable('2025-01-01'))
                    ->billable(true, ChangeMode::Allow, forRatio: true)
                    ->quantity(Quantity::create('KM')->billable(false, locked: true)),
            );

            self::assertSame($code, $created->code);
            self::assertSame(DimensionStatus::Active, $created->status);

            $read = $projects->get($code);

            self::assertSame('SDK test project', $read->name);
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
                $projects->create(ProjectInput::create($code, 'Duplicate'));
                self::fail('Expected a DimensionAlreadyExistsException.');
            } catch (DimensionAlreadyExistsException) {
                self::assertSame('SDK test project', $projects->get($code)->name, 'The existing project is unchanged.');
            }

            $projects->update(
                $read->edit()
                    ->name('SDK test project with a name of more than 40 characters')
                    ->shortName(null)
                    ->validTill(new DateTimeImmutable('2025-12-31'))
                    ->quantity(Quantity::create('Parking')->mandatory()),
            );

            $updated = $projects->get($code);

            self::assertSame('SDK test project with a name of more than 40 characters', $updated->name);
            self::assertNull($updated->shortName, 'An update clears values that were removed.');
            self::assertEquals(new DateTimeImmutable('2025-12-31'), $updated->projects->validTill);
            self::assertSame(['KM', 'Parking'], array_map(static fn (Quantity $q): string => $q->label, $updated->projects->quantities));
            self::assertSame('Created by the twinfield-php write tests', $updated->projects->invoiceDescription, 'edit() keeps other values.');

            self::assertSame(DeleteResult::Deleted, $projects->delete($code));
            self::assertSame(DimensionStatus::Deleted, $projects->get($code)->status);

            $projects->activate($code);
            self::assertSame(DimensionStatus::Active, $projects->get($code)->status);
        } finally {
            if ($projects->find($code)?->status === DimensionStatus::Active) {
                $projects->delete($code);
            }
        }
    }

    private function unusedCode(): string
    {
        for ($number = 9999; $number > 9900; $number--) {
            $code = sprintf('P%04d', $number);

            if ($this->twinfield->projects()->find($code) === null) {
                return $code;
            }
        }

        self::fail('No unused project code P9901–P9999.');
    }
}
