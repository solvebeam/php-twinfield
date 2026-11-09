<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Activity;

use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\ProjectSettings;
use SolveBeam\Twinfield\Dimension\TimeAndExpensesSettings;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * An activity to create or update. Immutable: every method returns a new input.
 *
 *   $input = ActivityInput::create('A001', 'Deploy API documentation')
 *       ->shortName('API docs')
 *       ->customer('1010')
 *       ->rate('T001', ChangeMode::Disallow)
 *       ->billable(true)
 *       ->quantity(Quantity::create('KM')->rate('KM'));
 *
 * For an existing activity, start from `$activity->edit()`, so all current values are kept.
 * Settings you leave out are inherited from the project or user level.
 */
final readonly class ActivityInput
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
            throw new InvalidArgumentException('Activity name must not be empty.');
        }

        XmlValue::maxLength('activity name', $name, 40);

        if ($shortName !== null) {
            XmlValue::maxLength('activity short name', $shortName, 20);
        }

        if ($vatCode !== null) {
            XmlValue::vatCode($vatCode);
        }
    }

    /**
     * @param string $code Code in the format of the ACT dimension type of the company, e.g. `A001`.
     * @param string $name Description, at most 40 characters.
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
     * VAT code for this activity; it applies even when the project has another VAT code. Null removes it.
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
     * The `<dimension>` request to save this activity in a company.
     */
    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('dimension');
        $dimension = $xml->root();

        $xml->add($dimension, 'office', $company);
        $xml->add($dimension, 'type', 'ACT');
        $xml->add($dimension, 'code', $this->code);
        $xml->add($dimension, 'name', $this->name);
        $xml->add($dimension, 'shortname', $this->shortName);

        $financials = $xml->add($dimension, 'financials');
        $xml->add($financials, 'vatcode', $this->vatCode);

        $this->projects->appendTo($xml, $dimension);

        return $xml;
    }
}
