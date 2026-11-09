<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Project;

use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Dimension\DimensionRequests;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Twinfield;

/**
 * Projects (dimension type `PRJ`) of the company: in Twinfield Time & Expenses, hours are booked
 * on an activity of a project. In the ledger, projects are dimensions at level 3.
 *
 *   $projects = $twinfield->company('1000')->projects();
 *
 *   foreach ($projects->search()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $project = $projects->get('P0001');
 *
 *   $projects->create(
 *       ProjectInput::create('P0100', 'Website redesign')
 *           ->customer('1010', ChangeMode::Disallow)
 *           ->rate('T001')
 *           ->billable(true),
 *   );
 *   $projects->update($project->edit()->validTill(new DateTimeImmutable('2026-12-31')));
 *   $projects->delete('P0100');
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/projects/
 */
final readonly class ProjectResource
{
    private DimensionRequests $requests;

    public function __construct(Twinfield $twinfield)
    {
        $this->requests = new DimensionRequests($twinfield, 'PRJ', 'Project', 'Projects');
    }

    /**
     * Search projects by code and name, e.g. `search('P*')->limit(10)->get()`.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<ProjectSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        return $this->requests->search($pattern, ProjectSummary::fromFinderRow(...));
    }

    /**
     * @throws DimensionNotFoundException When there is no project with this code.
     */
    public function get(string $code): Project
    {
        return Project::fromXml($this->requests->read($code), $this->requests->organisation());
    }

    /**
     * The project, or null when there is no project with this code.
     */
    public function find(string $code): ?Project
    {
        try {
            return $this->get($code);
        } catch (DimensionNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new project. Reads first, so an existing project is never overwritten.
     *
     * @throws DimensionAlreadyExistsException When the code is already in use.
     */
    public function create(ProjectInput $project): Project
    {
        if ($this->find($project->code) !== null) {
            throw new DimensionAlreadyExistsException('PRJ', $project->code);
        }

        return $this->save($project);
    }

    /**
     * Updates an existing project with all values of the input. Start from
     * `$project->edit()` to keep the values you do not change.
     *
     * @throws DimensionNotFoundException When there is no project with this code.
     */
    public function update(ProjectInput $project): Project
    {
        $this->get($project->code);

        return $this->save($project);
    }

    /**
     * Creates the project, or updates it when the code exists (one request, no check).
     */
    public function save(ProjectInput $project): Project
    {
        return Project::fromXml(
            $this->requests->save($project->toXml($this->requests->company())),
            $this->requests->organisation(),
        );
    }

    /**
     * Deletes the project. When it is in use, Twinfield hides it instead.
     *
     * @throws DimensionNotFoundException When there is no project with this code.
     */
    public function delete(string $code): DeleteResult
    {
        return $this->requests->delete($code);
    }

    /**
     * Makes a deleted or hidden project active again.
     *
     * @throws DimensionNotFoundException When there is no project with this code.
     */
    public function activate(string $code): void
    {
        $this->requests->activate($code);
    }
}
