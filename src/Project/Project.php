<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Project;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Dimension\ProjectSettings;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A project (dimension type `PRJ`) as Twinfield returns it.
 *
 *   $project = $twinfield->projects()->get('P0001');
 *   echo $project->code, ' ', $project->name, PHP_EOL;
 *   echo $project->projects->customer->code ?? 'customer from the activity', PHP_EOL;
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/projects/
 */
final readonly class Project
{
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Code in the format of the PRJ dimension type, e.g. `P0001`. */
        public string $code,
        /** Unique ID of the dimension, e.g. `6d7bfbf1-58ce-4699-9a78-4fb50bc545be`. */
        public string $uid,
        /** Description, at most 80 characters. */
        public string $name,
        /** Short description, at most 20 characters; null when not set. */
        public ?string $shortName,
        public DimensionStatus $status,
        /** True when the project is used in a transaction. Deleting it then hides it. */
        public bool $inUse,
        public DimensionBehaviour $behaviour,
        /** Number of times the settings were changed. */
        public int $touched,
        /** Last change. Null when Twinfield leaves it out: it is not in every documented response. */
        public ?DateTimeImmutable $modified,
        /**
         * VAT code for all activities of the project; an activity with its own VAT code uses that
         * one instead. Null when not set.
         */
        public ?string $vatCode,
        /** Time & expenses settings: valid dates, customer, rate, billable, quantities, … */
        public ProjectSettings $projects,
    ) {
        foreach (['code' => $code, 'uid' => $uid, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Project %s must not be empty.', $field));
            }
        }

        if ($touched < 0) {
            throw new InvalidArgumentException('Project touched count must be 0 or more.');
        }
    }

    /**
     * From a `<dimension>` element of a read or save response.
     *
     * @internal
     */
    public static function fromXml(XmlElement $dimension, Organisation $organisation): self
    {
        $type = $dimension->string('type');

        if ($type !== 'PRJ') {
            throw $dimension->child('type')->invalid('PRJ');
        }

        return new self(
            company: new CompanyReference($organisation, $dimension->string('office')),
            code: $dimension->string('code'),
            uid: $dimension->string('uid'),
            name: $dimension->string('name'),
            // Optional in Twinfield: empty when no short name is set.
            shortName: $dimension->nullableString('shortname'),
            status: $dimension->enumAttribute('status', DimensionStatus::class),
            inUse: $dimension->bool('inuse'),
            behaviour: $dimension->enum('behaviour', DimensionBehaviour::class),
            touched: $dimension->int('touched'),
            // Not in the save response of the documentation, but in every read response.
            modified: $dimension->nullableChild('modified') === null ? null : $dimension->dateTime('modified'),
            // Optional in Twinfield: an empty <vatcode/> when no VAT code is set.
            vatCode: $dimension->child('financials')->nullableString('vatcode'),
            projects: ProjectSettings::fromXml($dimension->child('projects')),
        );
    }

    /**
     * Input with all values of this project, to change and pass to update() or save().
     *
     *   $projects->update($project->edit()->validTill(new DateTimeImmutable('2026-12-31')));
     */
    public function edit(): ProjectInput
    {
        return ProjectInput::create($this->code, $this->name)
            ->shortName($this->shortName)
            ->vatCode($this->vatCode)
            ->projects($this->projects);
    }
}
