<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionType;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * Changes to a dimension type. Immutable: every method returns a new input.
 *
 *   $type = $types->get('DEB');
 *   $types->update(
 *       $type->edit()
 *           ->name('Klanten')
 *           ->mask('1[0-9][0-9][0-9][0-9]')
 *           ->addressLabel(6, 'IBAN'),
 *   );
 *
 * Twinfield can only change the name, the mask and the address labels; dimension types
 * cannot be created or deleted. So an input always starts from an existing type
 * (`$type->edit()`) and sends all its values again.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/dimension-types/#update
 */
final readonly class DimensionTypeInput
{
    private const NAME_MAX_LENGTH = 40;

    private const LABEL_MAX_LENGTH = 20;

    private function __construct(
        public string $code,
        public string $name,
        public CodeMask $mask,
        public AddressLabels $addressLabels,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Dimension type name must not be empty.');
        }

        XmlValue::maxLength('dimension type name', $name, self::NAME_MAX_LENGTH);

        foreach ($addressLabels->used() as $field => $label) {
            XmlValue::maxLength(sprintf('address label %d', $field), $label, self::LABEL_MAX_LENGTH);
        }
    }

    /**
     * All values of the dimension type; use `$type->edit()`.
     */
    public static function from(DimensionType $type): self
    {
        return new self($type->code, $type->name, $type->mask, $type->addressLabels);
    }

    /**
     * Name, at most 40 characters.
     */
    public function name(string $name): self
    {
        return new self($this->code, $name, $this->mask, $this->addressLabels);
    }

    /**
     * Mask that new codes must match, e.g. `1[0-9][0-9][0-9]` or `*` for any code.
     */
    public function mask(CodeMask|string $mask): self
    {
        return new self($this->code, $this->name, CodeMask::of($mask), $this->addressLabels);
    }

    /**
     * Label of address field 1–6, at most 20 characters. Null or an empty string removes it.
     */
    public function addressLabel(int $field, ?string $label): self
    {
        return new self($this->code, $this->name, $this->mask, $this->addressLabels->with($field, $label));
    }

    /**
     * All six address labels at once.
     */
    public function addressLabels(AddressLabels $labels): self
    {
        return new self($this->code, $this->name, $this->mask, $labels);
    }

    /**
     * The `<dimensiontype>` request to save this dimension type in a company. The short name
     * and levels are read-only, so they are not sent.
     */
    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('dimensiontype');
        $type = $xml->root();

        $xml->add($type, 'office', $company);
        $xml->add($type, 'code', $this->code);
        $xml->add($type, 'name', $this->name);
        $xml->add($type, 'mask', $this->mask->pattern);

        $address = $xml->add($type, 'address');

        foreach ($this->addressLabels->toArray() as $field => $label) {
            $xml->add($address, 'label' . $field, $label);
        }

        return $xml;
    }
}
