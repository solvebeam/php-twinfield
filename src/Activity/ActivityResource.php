<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Activity;

use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Dimension\DimensionRequests;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Twinfield;

/**
 * Activities (dimension type `ACT`) of the company: hours in Twinfield Time & Expenses
 * are booked on an activity of a project.
 *
 *   $activities = $twinfield->company('1000')->activities();
 *
 *   foreach ($activities->search()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $activity = $activities->get('A001');
 *
 *   $activities->create(ActivityInput::create('A002', 'Consultancy')->billable(true));
 *   $activities->update($activity->edit()->name('Development'));
 *   $activities->delete('A002');
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/activities/
 */
final readonly class ActivityResource
{
    private DimensionRequests $requests;

    public function __construct(Twinfield $twinfield)
    {
        $this->requests = new DimensionRequests($twinfield, 'ACT', 'Activity', 'Activities');
    }

    /**
     * Search activities by code and name, e.g. `search('A*')->limit(10)->get()`.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<ActivitySummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        return $this->requests->search($pattern, ActivitySummary::fromFinderRow(...));
    }

    /**
     * @throws DimensionNotFoundException When there is no activity with this code.
     */
    public function get(string $code): Activity
    {
        return Activity::fromXml($this->requests->read($code));
    }

    /**
     * The activity, or null when there is no activity with this code.
     */
    public function find(string $code): ?Activity
    {
        try {
            return $this->get($code);
        } catch (DimensionNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new activity. Reads first, so an existing activity is never overwritten.
     *
     * @throws DimensionAlreadyExistsException When the code is already in use.
     */
    public function create(ActivityInput $activity): Activity
    {
        if ($this->find($activity->code) !== null) {
            throw new DimensionAlreadyExistsException('ACT', $activity->code);
        }

        return $this->save($activity);
    }

    /**
     * Updates an existing activity with all values of the input. Start from
     * `$activity->edit()` to keep the values you do not change.
     *
     * @throws DimensionNotFoundException When there is no activity with this code.
     */
    public function update(ActivityInput $activity): Activity
    {
        $this->get($activity->code);

        return $this->save($activity);
    }

    /**
     * Creates the activity, or updates it when the code exists (one request, no check).
     */
    public function save(ActivityInput $activity): Activity
    {
        return Activity::fromXml($this->requests->save($activity->toXml($this->requests->company())));
    }

    /**
     * Deletes the activity. When it is in use, Twinfield hides it instead.
     *
     * @throws DimensionNotFoundException When there is no activity with this code.
     */
    public function delete(string $code): DeleteResult
    {
        return $this->requests->delete($code);
    }

    /**
     * Makes a deleted or hidden activity active again.
     *
     * @throws DimensionNotFoundException When there is no activity with this code.
     */
    public function activate(string $code): void
    {
        $this->requests->activate($code);
    }
}
