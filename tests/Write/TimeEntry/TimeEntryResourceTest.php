<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\TimeEntry;

use DateTimeImmutable;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\TimeEntry\TimeEntryInput;
use SolveBeam\Twinfield\TimeEntry\TimeEntryType;

/**
 * Saves a time entry of the current user on the first project and activity of the company, twice,
 * and prints what Twinfield answers, to record the behaviour the documentation leaves open.
 *
 * Leaves the time entry in the company: the Twinfield documentation has no delete for time
 * entries. Needs a company with time and expenses, a project and an activity.
 */
final class TimeEntryResourceTest extends WriteTestCase
{
    public function testSavesATimeEntryTwice(): void
    {
        $user = $this->twinfield->users()->current()->code;
        $project = $this->twinfield->projects()->search()->limit(1)->get()->items[0]->code
            ?? self::markTestSkipped('The company has no projects.');
        $activity = $this->twinfield->activities()->search()->limit(1)->get()->items[0]->code
            ?? self::markTestSkipped('The company has no activities.');
        $date = new DateTimeImmutable('today');

        $entries = $this->twinfield->timeEntries();

        $first = $entries->save(
            TimeEntryInput::direct($user, $date, $project, $activity)->time(15, description: 'PHP SDK write test'),
        );

        self::assertSame(TimeEntryType::Direct, $first->type);
        self::assertSame($user, $first->user->code);
        self::assertSame($project, $first->project?->code);
        self::assertSame($activity, $first->activity?->code);
        self::assertSame($date->format('Ymd'), $first->date->format('Ymd'));
        self::assertCount(1, $first->timeLines);
        self::assertSame(15, $first->timeLines[0]->duration);

        fwrite(STDERR, sprintf(
            "Duration 15: internal %s, external %s, rate %s (%s per %s units), billable %s\n",
            $first->timeLines[0]->internalValue,
            $first->timeLines[0]->externalValue,
            $first->timeLines[0]->rate->code,
            $first->timeLines[0]->rate->internalPrice,
            $first->timeLines[0]->rate->units,
            $first->timeLines[0]->billable ? 'yes' : 'no',
        ));

        // Same user, date, project and activity: does Twinfield update the entry or add a line?
        $second = $entries->save(
            TimeEntryInput::direct($user, $date, $project, $activity)->time(30, description: 'PHP SDK write test 2'),
        );

        fwrite(STDERR, sprintf(
            "After a second save: %d time lines, ids %s, durations %s\n",
            count($second->timeLines),
            implode(', ', array_map(static fn ($line): int => $line->id, $second->timeLines)),
            implode(', ', array_map(static fn ($line): int => $line->duration, $second->timeLines)),
        ));
    }
}
