<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Activity;

use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\BillableSetting;
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\CodeSetting;
use SolveBeam\Twinfield\Dimension\ProjectSettings;
use SolveBeam\Twinfield\Dimension\Quantity;
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
            XmlValue::maxLength('VAT code', $vatCode, 16);
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
     * First date users can book hours on the activity; null for no limit.
     */
    public function validFrom(?DateTimeInterface $date): self
    {
        return $this->withProjects(['validFrom' => $date]);
    }

    /**
     * Last date users can book hours on the activity; null for no limit.
     */
    public function validTill(?DateTimeInterface $date): self
    {
        return $this->withProjects(['validTill' => $date]);
    }

    /**
     * Longer description for the invoice template, at most 128 characters. Null removes it.
     */
    public function invoiceDescription(?string $description): self
    {
        return $this->withProjects(['invoiceDescription' => $description === '' ? null : $description]);
    }

    /**
     * User who authorises hours on this activity, e.g. `authoriser('SUPER', ChangeMode::Disallow)`.
     * `authoriser(null, ChangeMode::Inherit)` inherits it from the project or user level.
     */
    public function authoriser(?string $user, ChangeMode $change = ChangeMode::Allow): self
    {
        return $this->withProjects(['authoriser' => new CodeSetting($user, $change)]);
    }

    /**
     * Customer to invoice the hours to, e.g. `customer('1010')`.
     * `customer(null, ChangeMode::Inherit)` inherits it from the project.
     */
    public function customer(?string $customer, ChangeMode $change = ChangeMode::Allow): self
    {
        return $this->withProjects(['customer' => new CodeSetting($customer, $change)]);
    }

    /**
     * Rate code for the hours, e.g. `rate('T001', ChangeMode::Disallow)`.
     * `rate(null, ChangeMode::Inherit)` inherits it from the project or user level.
     */
    public function rate(?string $rate, ChangeMode $change = ChangeMode::Allow): self
    {
        return $this->withProjects(['rate' => new CodeSetting($rate, $change)]);
    }

    /**
     * Whether hours are billable, and whether they count for the productivity ratio.
     * `billable(null, ChangeMode::Inherit)` inherits it from the project or user level.
     */
    public function billable(
        ?bool $billable = true,
        ChangeMode $change = ChangeMode::Allow,
        bool $forRatio = false,
    ): self
    {
        return $this->withProjects(['billable' => new BillableSetting($billable, $change, $forRatio)]);
    }

    /**
     * Adds an extra quantity, such as kilometres.
     */
    public function quantity(Quantity $quantity): self
    {
        return $this->withProjects(['quantities' => [...$this->projects->quantities, $quantity]]);
    }

    /**
     * Replaces all quantities; `quantities()` without arguments removes them.
     */
    public function quantities(Quantity ...$quantities): self
    {
        return $this->withProjects(['quantities' => array_values($quantities)]);
    }

    /**
     * Replaces all time & expenses settings at once.
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

    /**
     * @param array{
     *     validFrom?: ?DateTimeInterface,
     *     validTill?: ?DateTimeInterface,
     *     invoiceDescription?: ?string,
     *     authoriser?: CodeSetting,
     *     customer?: CodeSetting,
     *     billable?: BillableSetting,
     *     rate?: CodeSetting,
     *     quantities?: list<Quantity>,
     * } $changes
     */
    private function withProjects(array $changes): self
    {
        $projects = $this->projects;

        return $this->projects(new ProjectSettings(
            validFrom: array_key_exists('validFrom', $changes) ? $changes['validFrom'] : $projects->validFrom,
            validTill: array_key_exists('validTill', $changes) ? $changes['validTill'] : $projects->validTill,
            invoiceDescription: array_key_exists('invoiceDescription', $changes)
                ? $changes['invoiceDescription']
                : $projects->invoiceDescription,
            authoriser: $changes['authoriser'] ?? $projects->authoriser,
            customer: $changes['customer'] ?? $projects->customer,
            billable: $changes['billable'] ?? $projects->billable,
            rate: $changes['rate'] ?? $projects->rate,
            quantities: $changes['quantities'] ?? $projects->quantities,
        ));
    }
}
