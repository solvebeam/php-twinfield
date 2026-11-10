<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * An address of a customer or supplier, as Twinfield returns it.
 *
 * Every field except the type is optional in Twinfield (an empty element), so each one is
 * null when not set; even the name can be empty.
 *
 * `field1`–`field6` are free fields; their labels are set per dimension type. By default they
 * are: 1 attention of ("T.a.v."), 2 and 3 address lines, 4 VAT number, 5 chamber of commerce
 * number. Twinfield only accepts valid VAT numbers in field 4.
 *
 * To change it, call edit() and pass the result to `CustomerInput::address()`.
 */
final readonly class Address
{
    public function __construct(
        /** Sequence number within the customer, e.g. `1`. */
        public int $id,
        public AddressType $type,
        /** True for the default address; a customer has at most one. */
        public bool $default,
        /** Company name. */
        public ?string $name,
        /** ISO 3166-1 alpha-2 country code, e.g. `NL`. */
        public ?string $country,
        public ?string $city,
        public ?string $postcode,
        public ?string $telephone,
        public ?string $telefax,
        public ?string $email,
        /** Attention of, by default, e.g. `T.a.v. J. Jansen`. */
        public ?string $field1,
        /** First address line, by default, e.g. `Main street 1`. */
        public ?string $field2,
        /** Second address line, by default. */
        public ?string $field3,
        /** VAT number, e.g. `NL001234567B01`. */
        public ?string $field4,
        /** Chamber of commerce number, e.g. `12345678`. */
        public ?string $field5,
        public ?string $field6,
    ) {
        if ($id < 1) {
            throw new InvalidArgumentException(sprintf('Address id must be 1 or more, %d given.', $id));
        }
    }

    /**
     * From an `<address>` element of a dimension.
     *
     * @internal
     */
    public static function fromXml(XmlElement $address): self
    {
        // Empty elements, e.g. <telefax/>, when a value is not set.
        return new self(
            id: $address->intAttribute('id'),
            type: $address->enumAttribute('type', AddressType::class),
            default: $address->boolAttribute('default'),
            name: $address->nullableString('name'),
            country: $address->nullableString('country'),
            city: $address->nullableString('city'),
            postcode: $address->nullableString('postcode'),
            telephone: $address->nullableString('telephone'),
            telefax: $address->nullableString('telefax'),
            email: $address->nullableString('email'),
            field1: $address->nullableString('field1'),
            field2: $address->nullableString('field2'),
            field3: $address->nullableString('field3'),
            field4: $address->nullableString('field4'),
            field5: $address->nullableString('field5'),
            field6: $address->nullableString('field6'),
        );
    }

    /**
     * VAT number (field 4).
     */
    public function vatNumber(): ?string
    {
        return $this->field4;
    }

    /**
     * Chamber of commerce number (field 5).
     */
    public function chamberOfCommerceNumber(): ?string
    {
        return $this->field5;
    }

    /**
     * Input with all values of this address, including its id, to change and pass to
     * `CustomerInput::address()`.
     */
    public function edit(): AddressInput
    {
        return AddressInput::create($this->type)
            ->id($this->id)
            ->default($this->default)
            ->name($this->name)
            ->country($this->country)
            ->city($this->city)
            ->postcode($this->postcode)
            ->telephone($this->telephone)
            ->telefax($this->telefax)
            ->email($this->email)
            ->field(1, $this->field1)
            ->field(2, $this->field2)
            ->field(3, $this->field3)
            ->field(4, $this->field4)
            ->field(5, $this->field5)
            ->field(6, $this->field6);
    }
}
