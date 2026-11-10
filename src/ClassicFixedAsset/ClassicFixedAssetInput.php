<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset;

use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Period\Period;

/**
 * A fixed asset to create or update. Immutable: every method returns a new input.
 *
 *   $input = ClassicFixedAssetInput::create('Delivery van')
 *       ->method('5YLIN')
 *       ->costCenter('00000')
 *       ->purchaseDate(new DateTimeImmutable('2026-03-15'))
 *       ->depreciateFrom(Period::of(2026, 4))
 *       ->periods(60)
 *       ->residualValue('2500.00')
 *       ->freeText(1, 'VIN WDB9066331S123456')
 *       ->group('VEHICLES');
 *
 * Without code() Twinfield gives the asset the next free code of the AST dimension type.
 *
 * Only what you set is sent: anything else gets the default (of the asset method) when
 * creating, and stays as it is when updating. Setting a value to null sends it empty. For an
 * existing asset, start from `$asset->edit()`.
 *
 * Twinfield locks settings depending on the status of the asset, e.g. the begin period once it
 * is active. `edit()` leaves locked settings out, and setting one on that input throws a
 * LogicException before anything is sent.
 *
 * The status, sell date and transaction lines cannot be set: Twinfield always locks the status
 * and sell date, and links transaction lines when you book a transaction with the asset as
 * dimension 3.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/fixed-assets/
 */
final readonly class ClassicFixedAssetInput
{
    /**
     * Elements in the order of the documentation; `a/b` is element `b` in `a`.
     */
    private const ELEMENTS = [
        'financials/substitutionlevel',
        'financials/substitutewith',
        'financials/vatcode',
        'fixedassets/residualvalue',
        'fixedassets/stopvalue',
        'fixedassets/method',
        'fixedassets/beginperiod',
        'fixedassets/purchasedate',
        'fixedassets/freetext1',
        'fixedassets/freetext2',
        'fixedassets/freetext3',
        'fixedassets/freetext4',
        'fixedassets/freetext5',
        'fixedassets/nrofperiods',
        'fixedassets/percentage',
        'fixedassets/lastdepreciation',
    ];

    /**
     * @param array<string, ?string> $values Element (see ELEMENTS) => value; null sends an
     *     empty element.
     * @param ?list<string> $groups Null when not set: the groups are not sent.
     * @param list<ClassicFixedAssetField> $lockedFields Settings Twinfield has locked; see edit().
     */
    private function __construct(
        /** Code; null to let Twinfield give the next free code. */
        public ?string $code,
        public string $name,
        private array $values,
        public ?array $groups,
        public array $lockedFields,
    ) {
        if ($code !== null) {
            XmlValue::code($code);
        }

        if ($name === '') {
            throw new InvalidArgumentException('Fixed asset name must not be empty.');
        }

        XmlValue::maxLength('fixed asset name', $name, 80);
    }

    /**
     * @param string $name Name, at most 80 characters, e.g. `Delivery van`.
     */
    public static function create(string $name): self
    {
        return new self(null, $name, [], null, []);
    }

    /**
     * Code in the format of the AST dimension type of the company, e.g. `60010`; null lets
     * Twinfield give the next free code.
     */
    public function code(?string $code): self
    {
        return $this->with(['code' => $code === '' ? null : $code]);
    }

    /**
     * Name, at most 80 characters.
     */
    public function name(string $name): self
    {
        return $this->with(['name' => $name]);
    }

    /**
     * Cost center (dimension 2) of the asset, e.g. `00000`.
     */
    public function costCenter(string $code): self
    {
        return $this->setMany([
            'financials/substitutionlevel' => '2',
            'financials/substitutewith' => XmlValue::code($code),
        ]);
    }

    /**
     * Default VAT code, e.g. `IH`; null for none.
     */
    public function vatCode(?string $vatCode): self
    {
        $vatCode = $vatCode === null || $vatCode === '' ? null : XmlValue::vatCode($vatCode);

        return $this->set('financials/vatcode', $vatCode);
    }

    /**
     * Asset method code, e.g. `5YLIN`: how and on which accounts the asset is depreciated.
     * See `$twinfield->classicAssetMethods()`.
     */
    public function method(string $code): self
    {
        return $this->guarded(ClassicFixedAssetField::Method, XmlValue::code($code));
    }

    /**
     * Value of the asset at the end of the depreciation, e.g. `'2500.00'`.
     *
     * @param Amount|numeric-string $value
     */
    public function residualValue(Amount|string $value): self
    {
        return $this->guarded(ClassicFixedAssetField::ResidualValue, self::notNegative('residual value', $value));
    }

    /**
     * Book value at which depreciation stops, e.g. `'0.00'`.
     *
     * @param Amount|numeric-string $value
     */
    public function stopValue(Amount|string $value): self
    {
        return $this->guarded(ClassicFixedAssetField::StopValue, self::notNegative('stop value', $value));
    }

    /**
     * First period of depreciation (`beginperiod`), e.g. `Period::of(2026, 4)`.
     */
    public function depreciateFrom(?Period $period): self
    {
        return $this->guarded(ClassicFixedAssetField::DepreciateFrom, $period?->__toString());
    }

    public function purchaseDate(?DateTimeInterface $date): self
    {
        return $this->guarded(ClassicFixedAssetField::PurchaseDate, $date === null ? null : XmlValue::date($date));
    }

    /**
     * Free text field 1 to 5. The asset method sets the label and type (text or amount) of
     * each field, e.g. field 1 "Serial number".
     */
    public function freeText(int $id, ?string $value): self
    {
        return $this->guarded(ClassicFixedAssetField::freeText($id), $value === '' ? null : $value);
    }

    /**
     * Number of periods over which the asset is depreciated, e.g. `60`. Null sends it empty.
     */
    public function periods(?int $periods): self
    {
        if ($periods !== null && $periods < 1) {
            throw new InvalidArgumentException(sprintf('Number of periods must be 1 or more, %d given.', $periods));
        }

        return $this->guarded(ClassicFixedAssetField::Periods, $periods === null ? null : (string) $periods);
    }

    /**
     * Depreciation percentage per year, e.g. `'20'` or `'12.5'`, for asset methods that
     * calculate with a percentage. Null sends it empty.
     *
     * @param ?numeric-string $percentage
     */
    public function percentage(?string $percentage): self
    {
        if ($percentage !== null && (preg_match('~^\d+(\.\d+)?$~', $percentage) !== 1 || (float) $percentage > 100)) {
            throw new InvalidArgumentException(sprintf(
                'Percentage must be a number from 0 to 100 with a dot, e.g. "12.5", "%s" given.',
                $percentage,
            ));
        }

        return $this->guarded(ClassicFixedAssetField::Percentage, $percentage);
    }

    /**
     * Period of the last depreciation, e.g. for an asset that was depreciated before it was
     * added to Twinfield.
     */
    public function lastDepreciation(?Period $period): self
    {
        return $this->guarded(ClassicFixedAssetField::LastDepreciation, $period?->__toString());
    }

    /**
     * Adds the asset to a dimension group, e.g. `VEHICLES`.
     */
    public function group(string $group): self
    {
        return $this->groups(...array_unique([...$this->groups ?? [], $group]));
    }

    /**
     * Replaces all dimension groups; `groups()` without arguments removes them.
     */
    public function groups(string ...$groups): self
    {
        foreach ($groups as $group) {
            XmlValue::code($group);
        }

        return $this->with(['groups' => array_values($groups)]);
    }

    /**
     * Marks settings as locked: their values are not sent, and setting them throws. edit()
     * uses this with the settings Twinfield has locked.
     *
     * @internal
     */
    public function withLockedFields(ClassicFixedAssetField ...$fields): self
    {
        $values = $this->values;

        foreach ($fields as $field) {
            unset($values['fixedassets/' . $field->value]);
        }

        return $this->with(['values' => $values, 'lockedFields' => array_values($fields)]);
    }

    public function isLocked(ClassicFixedAssetField $field): bool
    {
        return in_array($field, $this->lockedFields, true);
    }

    /**
     * The value that will be sent for an element, e.g. `value('fixedassets/nrofperiods')`, or
     * null when it is empty or not set. See has() to tell these apart.
     */
    public function value(string $element): ?string
    {
        return $this->values[$element] ?? null;
    }

    /**
     * True when the element will be sent, e.g. `has('fixedassets/nrofperiods')`.
     */
    public function has(string $element): bool
    {
        return array_key_exists($element, $this->values);
    }

    /**
     * The `<dimension>` request to save this fixed asset in a company.
     */
    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('dimension');
        $dimension = $xml->root();

        $xml->add($dimension, 'office', $company);
        $xml->add($dimension, 'type', 'AST');

        if ($this->code !== null) {
            $xml->add($dimension, 'code', $this->code);
        }

        $xml->add($dimension, 'name', $this->name);

        /** @var array<string, DOMElement> $parents */
        $parents = [];

        foreach (self::ELEMENTS as $path) {
            if (!array_key_exists($path, $this->values)) {
                continue;
            }

            [$parentName, $name] = explode('/', $path);
            $parents[$parentName] ??= $xml->add($dimension, $parentName);

            $xml->add($parents[$parentName], $name, $this->values[$path]);
        }

        if ($this->groups !== null) {
            $element = $xml->add($dimension, 'groups');

            foreach ($this->groups as $group) {
                $xml->add($element, 'group', $group);
            }
        }

        return $xml;
    }

    /**
     * Sets a fixed asset setting, unless Twinfield has locked it.
     *
     * @throws LogicException When the setting is locked.
     */
    private function guarded(ClassicFixedAssetField $field, ?string $value): self
    {
        if ($this->isLocked($field)) {
            throw new LogicException(sprintf(
                'The %s of fixed asset %s is locked: Twinfield does not allow changing it, '
                    . 'e.g. because the asset is active.',
                $field->label(),
                $this->code ?? $this->name,
            ));
        }

        return $this->set('fixedassets/' . $field->value, $value);
    }

    private function set(string $element, ?string $value): self
    {
        return $this->setMany([$element => $value]);
    }

    /**
     * @param array<string, ?string> $values
     */
    private function setMany(array $values): self
    {
        return $this->with(['values' => [...$this->values, ...$values]]);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }

    /**
     * @param Amount|numeric-string $value
     */
    private static function notNegative(string $field, Amount|string $value): string
    {
        $amount = Amount::of($value);

        if ($amount->isNegative()) {
            throw new InvalidArgumentException(sprintf('The %s must not be negative, %s given.', $field, $amount));
        }

        return $amount->value;
    }
}
