<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * Time & expenses settings of an activity or project (the `projects` element).
 *
 * Every setting is optional in Twinfield: by default everything is inherited (see ChangeMode)
 * and the activity or project is valid on every date.
 */
final readonly class ProjectSettings
{
    public ?DateTimeImmutable $validFrom;

    public ?DateTimeImmutable $validTill;

    /**
     * @param list<Quantity> $quantities
     */
    public function __construct(
        ?DateTimeInterface $validFrom = null,
        ?DateTimeInterface $validTill = null,
        /** Longer description for the invoice template, at most 128 characters. */
        public ?string $invoiceDescription = null,
        public CodeSetting $authoriser = new CodeSetting(null, ChangeMode::Inherit),
        public CodeSetting $customer = new CodeSetting(null, ChangeMode::Inherit),
        public BillableSetting $billable = new BillableSetting(null, ChangeMode::Inherit),
        public CodeSetting $rate = new CodeSetting(null, ChangeMode::Inherit),
        public array $quantities = [],
    ) {
        // Dates only: Twinfield stores yyyymmdd.
        $this->validFrom = $validFrom === null ? null : XmlValue::toDate(XmlValue::date($validFrom));
        $this->validTill = $validTill === null ? null : XmlValue::toDate(XmlValue::date($validTill));

        if ($invoiceDescription !== null) {
            XmlValue::maxLength('invoice description', $invoiceDescription, 128);
        }
    }

    public static function fromXml(XmlElement $projects): self
    {
        return new self(
            validFrom: $projects->nullableDate('validfrom'),
            validTill: $projects->nullableDate('validtill'),
            // Optional in Twinfield: empty when no invoice description is set.
            invoiceDescription: $projects->nullableString('invoicedescription'),
            authoriser: self::codeSetting($projects->child('authoriser')),
            customer: self::codeSetting($projects->child('customer')),
            billable: self::billableSetting($projects->child('billable')),
            rate: self::codeSetting($projects->child('rate')),
            quantities: array_map(self::quantity(...), $projects->child('quantities')->list('quantity')),
        );
    }

    /**
     * Adds the complete `projects` element, so an update also clears values that were removed.
     */
    public function appendTo(XmlBuilder $xml, DOMElement $parent): void
    {
        $projects = $xml->add($parent, 'projects');

        $xml->add($projects, 'validfrom', $this->validFrom === null ? null : XmlValue::date($this->validFrom));
        $xml->add($projects, 'validtill', $this->validTill === null ? null : XmlValue::date($this->validTill));
        $xml->add($projects, 'invoicedescription', $this->invoiceDescription);

        self::appendCodeSetting($xml, $projects, 'authoriser', $this->authoriser);
        self::appendCodeSetting($xml, $projects, 'customer', $this->customer);

        $xml->add(
            $projects,
            'billable',
            $this->billable->billable === null ? null : XmlValue::boolean($this->billable->billable),
            [
                ...self::changeAttributes($this->billable->change),
                'forratio' => XmlValue::boolean($this->billable->forRatio),
            ],
        );

        self::appendCodeSetting($xml, $projects, 'rate', $this->rate);

        $quantities = $xml->add($projects, 'quantities');

        foreach ($this->quantities as $quantity) {
            $element = $xml->add($quantities, 'quantity');

            $xml->add($element, 'label', $quantity->label);
            $xml->add($element, 'rate', $quantity->rate);
            $xml->add($element, 'billable', XmlValue::boolean($quantity->billable), [
                'locked' => XmlValue::boolean($quantity->billableLocked),
            ]);
            $xml->add($element, 'mandatory', XmlValue::boolean($quantity->mandatory));
        }
    }

    private static function codeSetting(XmlElement $element): CodeSetting
    {
        return new CodeSetting(
            // Empty when no code is set, e.g. when the setting is inherited.
            $element->text() === '' ? null : $element->text(),
            self::changeMode($element),
        );
    }

    private static function billableSetting(XmlElement $element): BillableSetting
    {
        return new BillableSetting(
            // Empty when the setting is inherited.
            match ($element->text()) {
                '' => null,
                'true' => true,
                'false' => false,
                default => throw $element->invalid('a boolean (true or false) or empty'),
            },
            self::changeMode($element),
            $element->boolAttribute('forratio'),
        );
    }

    private static function quantity(XmlElement $quantity): Quantity
    {
        $billable = $quantity->child('billable');

        return new Quantity(
            label: $quantity->string('label'),
            // Optional in Twinfield: a quantity without rate.
            rate: $quantity->nullableString('rate'),
            billable: $quantity->bool('billable'),
            billableLocked: $billable->boolAttribute('locked'),
            mandatory: $quantity->bool('mandatory'),
        );
    }

    private static function changeMode(XmlElement $element): ChangeMode
    {
        return ChangeMode::fromXml(
            $element->boolAttribute('locked'),
            $element->boolAttribute('inherit'),
            $element->path,
        );
    }

    private static function appendCodeSetting(
        XmlBuilder $xml,
        DOMElement $parent,
        string $name,
        CodeSetting $setting,
    ): void {
        $xml->add($parent, $name, $setting->code, self::changeAttributes($setting->change));
    }

    /**
     * @return array{locked: string, inherit: string}
     */
    private static function changeAttributes(ChangeMode $change): array
    {
        return [
            'locked' => XmlValue::boolean($change->locked()),
            'inherit' => XmlValue::boolean($change->inherit()),
        ];
    }
}
