<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The address of an office (`general/address`).
 *
 * Every field is optional in Twinfield (an empty element), so each one is null when not set.
 * `field1`–`field6` are free address lines; usually `field1` holds the street and number.
 */
final readonly class OfficeAddress
{
    public function __construct(
        /** Usually the street and number, e.g. `Main street 1`. */
        public ?string $field1,
        public ?string $field2,
        public ?string $field3,
        public ?string $field4,
        public ?string $field5,
        public ?string $field6,
        public ?string $postcode,
        public ?string $city,
        /** Country, e.g. `NL` with name `Nederland`. */
        public ?NamedReference $country,
        public ?string $telephone,
        public ?string $fax,
    ) {
    }

    public static function fromXml(XmlElement $address): self
    {
        return new self(
            field1: $address->nullableString('field1'),
            field2: $address->nullableString('field2'),
            field3: $address->nullableString('field3'),
            field4: $address->nullableString('field4'),
            field5: $address->nullableString('field5'),
            field6: $address->nullableString('field6'),
            postcode: $address->nullableString('postcode'),
            city: $address->nullableString('city'),
            country: NamedReference::nullable($address->child('country')),
            telephone: $address->nullableString('telephone'),
            fax: $address->nullableString('fax'),
        );
    }
}
