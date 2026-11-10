<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Project;

use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\ProjectSettings;
use SolveBeam\Twinfield\Dimension\TimeAndExpensesSettings;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A project to create or update. Immutable: every method returns a new input.
 *
 *   $input = ProjectInput::create('P0100', 'Website redesign')
 *       ->shortName('Website')
 *       ->customer('1010', ChangeMode::Disallow)
 *       ->rate('T001')
 *       ->billable(true)
 *       ->validFrom(new DateTimeImmutable('2026-01-01'))
 *       ->quantity(Quantity::create('KM')->rate('KM'));
 *
 * For an existing project, start from `$project->edit()`, so all current values are kept.
 * Settings you leave out are inherited from the activity or user level.
 *
 * Twinfield links every project to a customer: set it here, or leave it inherited so the
 * customer of the activity is used.
 */
final readonly class ProjectInput
{
    use TimeAndExpensesSettings;

    private function __construct(
        public string $code,
        public string $name,
        public ?string $shortName,
        public ?string $vatCode,
        public ProjectSettings $projects,
    ) {
        XmlValue::code($code);

        if ($name === '') {
            throw new InvalidArgumentException('Project name must not be empty.');
        }

        XmlValue::maxLength('project name', $name, 80);

        if ($shortName !== null) {
            XmlValue::maxLength('project short name', $shortName, 20);
        }

        if ($vatCode !== null) {
            XmlValue::vatCode($vatCode);
        }
    }

    /**
     * @param string $code Code in the format of the PRJ dimension type of the company, e.g. `P0001`.
     * @param string $name Description, at most 80 characters.
     */
    public static function create(string $code, string $name): self
    {
        return new self($code, $name, null, null, new ProjectSettings());
    }

    public function name(string $name): self
    {
        return new self($this->code, $name, $this->shortName, $this->vatCode, $this->projects);
    }

    /**
     * At most 20 characters; null removes it.
     */
    public function shortName(?string $shortName): self
    {
        $shortName = $shortName === '' ? null : $shortName;

        return new self($this->code, $this->name, $shortName, $this->vatCode, $this->projects);
    }

    /**
     * VAT code for all activities of the project; an activity with its own VAT code uses that
     * one instead. Null removes it.
     */
    public function vatCode(?string $vatCode): self
    {
        return new self($this->code, $this->name, $this->shortName, $vatCode === '' ? null : $vatCode, $this->projects);
    }

    /**
     * Replaces all time & expenses settings at once. The other setters (validFrom(), customer(),
     * billable(), quantity(), …) change one setting.
     */
    public function projects(ProjectSettings $projects): self
    {
        return new self($this->code, $this->name, $this->shortName, $this->vatCode, $projects);
    }

    /**
     * The `<dimension>` request to save this project in a company.
     *
     * @internal
     */
    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('dimension');
        $dimension = $xml->root();

        $xml->add($dimension, 'office', $company);
        $xml->add($dimension, 'type', 'PRJ');
        $xml->add($dimension, 'code', $this->code);
        $xml->add($dimension, 'name', $this->name);
        $xml->add($dimension, 'shortname', $this->shortName);

        $financials = $xml->add($dimension, 'financials');
        $xml->add($financials, 'vatcode', $this->vatCode);

        $this->projects->appendTo($xml, $dimension);

        return $xml;
    }
}
