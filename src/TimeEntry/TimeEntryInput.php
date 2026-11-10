<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use DateTimeImmutable;
use DateTimeInterface;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A time entry to save: the time and quantities of one user on one date, for a project and an
 * activity.
 *
 *   TimeEntryInput::direct('JDOE', new DateTimeImmutable('2026-11-10'), 'P0099', 'A010')
 *       ->time(240, description: 'Deploy')
 *       ->quantity('1.5', rate: 'KM');
 *
 * Immutable: every method returns a new input.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/time/
 */
final readonly class TimeEntryInput
{
    /**
     * @param list<TimeLineInput|QuantityLineInput> $lines
     */
    private function __construct(
        public TimeEntryType $type,
        /** Code of the user the time is entered for. */
        public string $user,
        public DateTimeImmutable $date,
        /** Project code (`prj1`). */
        public ?string $project,
        /** Activity code (`prj2`). */
        public ?string $activity,
        public array $lines,
    ) {
    }

    public static function create(
        TimeEntryType $type,
        string $user,
        DateTimeInterface $date,
        ?string $project = null,
        ?string $activity = null,
    ): self {
        XmlValue::code($user);

        foreach ([$project, $activity] as $code) {
            if ($code !== null) {
                XmlValue::code($code);
            }
        }

        return new self($type, $user, DateTimeImmutable::createFromInterface($date), $project, $activity, []);
    }

    /**
     * Time on a project.
     */
    public static function direct(string $user, DateTimeInterface $date, string $project, string $activity): self
    {
        return self::create(TimeEntryType::Direct, $user, $date, $project, $activity);
    }

    /**
     * Time that is not for a project of a customer.
     */
    public static function indirect(
        string $user,
        DateTimeInterface $date,
        ?string $project = null,
        ?string $activity = null,
    ): self {
        return self::create(TimeEntryType::Indirect, $user, $date, $project, $activity);
    }

    /**
     * Personal time, e.g. leave.
     */
    public static function personal(
        string $user,
        DateTimeInterface $date,
        ?string $project = null,
        ?string $activity = null,
    ): self {
        return self::create(TimeEntryType::Personal, $user, $date, $project, $activity);
    }

    /**
     * Adds a time line.
     *
     * @param int $duration See `TimeLineInput::$duration` for the unit.
     */
    public function time(int $duration, ?string $description = null, ?bool $billable = null, ?string $rate = null): self
    {
        return $this->line(new TimeLineInput($duration, $description, $billable, $rate));
    }

    /**
     * Adds a quantity line, e.g. kilometres.
     *
     * @param numeric-string $quantity
     */
    public function quantity(string $quantity, ?string $rate = null, ?bool $billable = null): self
    {
        return $this->line(new QuantityLineInput($quantity, $rate, $billable));
    }

    public function line(TimeLineInput|QuantityLineInput $line): self
    {
        return new self(
            $this->type,
            $this->user,
            $this->date,
            $this->project,
            $this->activity,
            [...$this->lines, $line],
        );
    }
}
