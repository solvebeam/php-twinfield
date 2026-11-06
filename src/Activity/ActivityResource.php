<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Activity;

use LogicException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

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
    public function __construct(
        private Twinfield $twinfield,
    ) {
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
        $query = $this->twinfield->finder(FinderType::Dimensions)->option('dimtype', 'ACT');

        return (new CodeNameSearch($query, ActivitySummary::fromFinderRow(...)))->pattern($pattern);
    }

    /**
     * @throws DimensionNotFoundException When there is no activity with this code.
     */
    public function get(string $code): Activity
    {
        $xml = XmlBuilder::create('read');
        $xml->add($xml->root(), 'type', 'dimensions');
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'dimtype', 'ACT');
        $xml->add($xml->root(), 'code', $code);

        return Activity::fromXml(XmlElement::root($this->send($xml, $code)));
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
        $result = $this->send($activity->toXml($this->company()), $activity->code);

        return Activity::fromXml(XmlElement::root($result));
    }

    /**
     * Deletes the activity. When it is in use, Twinfield hides it instead.
     *
     * @throws DimensionNotFoundException When there is no activity with this code.
     */
    public function delete(string $code): DeleteResult
    {
        $result = $this->changeStatus($code, DimensionStatus::Deleted);
        $status = XmlElement::root($result)->enumAttribute('status', DimensionStatus::class);

        return match ($status) {
            DimensionStatus::Deleted => DeleteResult::Deleted,
            DimensionStatus::Hidden => DeleteResult::Hidden,
            DimensionStatus::Active => throw new UnexpectedResponseException(sprintf(
                'Activity %s is still active after deleting it.',
                $code,
            )),
        };
    }

    /**
     * Makes a deleted or hidden activity active again.
     *
     * @throws DimensionNotFoundException When there is no activity with this code.
     */
    public function activate(string $code): void
    {
        $this->changeStatus($code, DimensionStatus::Active);
    }

    private function changeStatus(string $code, DimensionStatus $status): ProcessXmlResult
    {
        $xml = XmlBuilder::create('dimension', ['status' => $status->value]);
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'type', 'ACT');
        $xml->add($xml->root(), 'code', $code);

        return $this->send($xml, $code);
    }

    private function send(XmlBuilder $xml, string $code): ProcessXmlResult
    {
        try {
            return $this->twinfield->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            throw DimensionNotFoundException::fromProcessXml($exception, 'ACT', $code) ?? $exception;
        }
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Activities belong to a company: use company() first.');
    }
}
