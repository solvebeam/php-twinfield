<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionGroup;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A dimension group to create or update. Immutable: every method returns a new input.
 *
 *   $input = DimensionGroupInput::create('NL-CUSTOMERS', 'Dutch customers')
 *       ->shortName('NL customers')
 *       ->add('DEB', '1000', '1001', '1002')
 *       ->remove('DEB', '1001');
 *
 * The input is the complete group: saving it replaces the name, the short name and all
 * dimensions of the group. For an existing group, start from `$group->edit()`, which has
 * all current values and dimensions, so only what you change differs.
 *
 * A group may have dimensions of more than one type, e.g. customers and cost centers.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/dimension-groups/
 */
final readonly class DimensionGroupInput
{
    /**
     * @param array<string, array{type: string, code: string}> $dimensions By `type code`, in the order added.
     */
    private function __construct(
        public string $code,
        public string $name,
        public ?string $shortName,
        private array $dimensions,
    ) {
        XmlValue::code($code);

        if ($name === '') {
            throw new InvalidArgumentException('Dimension group name must not be empty.');
        }

        XmlValue::maxLength('dimension group name', $name, 40);

        if ($shortName !== null) {
            XmlValue::maxLength('dimension group short name', $shortName, 20);
        }
    }

    /**
     * @param string $code Dimension group code: 1-16 times A-Z, 0-9, `_`, `-` or `+`, e.g. `NL-CUSTOMERS`.
     * @param string $name Name, at most 40 characters.
     */
    public static function create(string $code, string $name): self
    {
        return new self($code, $name, null, []);
    }

    public function name(string $name): self
    {
        return new self($this->code, $name, $this->shortName, $this->dimensions);
    }

    /**
     * Short name, at most 20 characters. Null or an empty string clears it.
     */
    public function shortName(?string $shortName): self
    {
        $shortName = $shortName === '' ? null : $shortName;

        return new self($this->code, $this->name, $shortName, $this->dimensions);
    }

    /**
     * Adds dimensions of one type; dimensions already in the group stay where they are.
     *
     *   ->add('DEB', '1000', '1001')
     *
     * @param string $type Dimension type, e.g. `DEB`, `CRD`, `KPL` or `PNL`.
     * @param string ...$codes Codes of existing dimensions of that type.
     */
    public function add(string $type, string ...$codes): self
    {
        XmlValue::code($type);

        $dimensions = $this->dimensions;

        foreach ($codes as $code) {
            XmlValue::code($code);

            $dimensions[$type . ' ' . $code] ??= ['type' => $type, 'code' => $code];
        }

        return new self($this->code, $this->name, $this->shortName, $dimensions);
    }

    /**
     * Removes dimensions of one type from the group; codes that are not in it are ignored.
     *
     * @param string ...$codes
     */
    public function remove(string $type, string ...$codes): self
    {
        $dimensions = $this->dimensions;

        foreach ($codes as $code) {
            unset($dimensions[$type . ' ' . $code]);
        }

        return new self($this->code, $this->name, $this->shortName, $dimensions);
    }

    /**
     * Removes all dimensions, or all dimensions of one type, e.g. `clear('DEB')`.
     */
    public function clear(?string $type = null): self
    {
        $dimensions = $type === null ? [] : array_filter(
            $this->dimensions,
            static fn (array $dimension): bool => $dimension['type'] !== $type,
        );

        return new self($this->code, $this->name, $this->shortName, $dimensions);
    }

    /**
     * Whether the dimension with this type and code is in the input.
     */
    public function has(string $type, string $code): bool
    {
        return isset($this->dimensions[$type . ' ' . $code]);
    }

    /**
     * Dimensions to save, in the order added.
     *
     * @return list<array{type: string, code: string}>
     */
    public function dimensions(): array
    {
        return array_values($this->dimensions);
    }

    /**
     * The `<dimensiongroup>` request to save this group in a company.
     *
     * Always `status="active"`: without it, saving a deleted group keeps it deleted. The
     * short name and `<dimensions>` are always sent, so they replace the current ones
     * (an empty `<dimensions/>` removes all dimensions).
     */
    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('dimensiongroup', ['status' => DimensionGroupStatus::Active->value]);
        $group = $xml->root();

        $xml->add($group, 'office', $company);
        $xml->add($group, 'code', $this->code);
        $xml->add($group, 'name', $this->name);
        $xml->add($group, 'shortname', $this->shortName);

        $dimensions = $xml->add($group, 'dimensions');

        foreach ($this->dimensions as $dimension) {
            $element = $xml->add($dimensions, 'dimension');
            $xml->add($element, 'type', $dimension['type']);
            $xml->add($element, 'code', $dimension['code']);
        }

        return $xml;
    }
}
