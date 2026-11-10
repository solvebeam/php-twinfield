<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionType;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Labels of the free address fields `field1`–`field6` of the dimensions of a type, e.g. of
 * `Address::$field2` of customers. Null when a field has no label: Twinfield then does not
 * show it. By default customers and suppliers have: 1 attention of, 2 address, 3 postal
 * address, 4 VAT number and 5 chamber of commerce number.
 *
 *   $labels = $twinfield->dimensionTypes()->get('DEB')->addressLabels;
 *   echo $labels->field4, PHP_EOL;          // e.g. "BTW nummer"
 *   echo $labels->forField(4), PHP_EOL;     // the same
 */
final readonly class AddressLabels
{
    public function __construct(
        public ?string $field1 = null,
        public ?string $field2 = null,
        public ?string $field3 = null,
        public ?string $field4 = null,
        public ?string $field5 = null,
        public ?string $field6 = null,
    ) {
    }

    /**
     * From the `<address>` element of a dimension type, with `<label1>`–`<label6>`.
     *
     * @internal
     */
    public static function fromXml(XmlElement $address): self
    {
        // An empty label is valid: the field is not used.
        return new self(
            $address->nullableString('label1'),
            $address->nullableString('label2'),
            $address->nullableString('label3'),
            $address->nullableString('label4'),
            $address->nullableString('label5'),
            $address->nullableString('label6'),
        );
    }

    /**
     * Labels by field number 1–6, also the fields without a label (null).
     *
     * @param array<int, string|null> $labels
     */
    public static function fromArray(array $labels): self
    {
        $result = new self();

        foreach ($labels as $field => $label) {
            $result = $result->with($field, $label);
        }

        return $result;
    }

    /**
     * The label of field 1–6, or null when it has none.
     */
    public function forField(int $field): ?string
    {
        return $this->toArray()[self::field($field)];
    }

    /**
     * These labels with the label of one field changed; null or an empty string removes it.
     */
    public function with(int $field, ?string $label): self
    {
        $labels = $this->toArray();
        $labels[self::field($field)] = $label === '' ? null : $label;

        return new self(...array_values($labels));
    }

    /**
     * Labels by field number 1–6, null for fields without a label.
     *
     * @return array{1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string, 6: ?string}
     */
    public function toArray(): array
    {
        return [
            1 => $this->field1,
            2 => $this->field2,
            3 => $this->field3,
            4 => $this->field4,
            5 => $this->field5,
            6 => $this->field6,
        ];
    }

    /**
     * Only the fields that have a label, by field number.
     *
     * @return array<int<1, 6>, string>
     */
    public function used(): array
    {
        return array_filter($this->toArray(), static fn (?string $label): bool => $label !== null);
    }

    /**
     * @return int<1, 6>
     */
    private static function field(int $field): int
    {
        if ($field < 1 || $field > 6) {
            throw new InvalidArgumentException(sprintf('Address field must be 1 to 6, %d given.', $field));
        }

        return $field;
    }
}
