<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * An address of a customer or supplier to create or update. Immutable: every method returns
 * a new input.
 *
 *   $address = AddressInput::invoice('Example B.V.')
 *       ->field(1, 'T.a.v. J. Jansen')
 *       ->field(2, 'Main street 1')
 *       ->postcode('1234 AB')
 *       ->city('Amsterdam')
 *       ->country('NL')
 *       ->email('invoices@example.com')
 *       ->vatNumber('NL001234567B01')
 *       ->default();
 *
 * For an existing address, start from `$address->edit()`: it keeps the id, so Twinfield
 * changes that address instead of adding one. A value you set to null is cleared.
 */
final readonly class AddressInput
{
    private function __construct(
        /** Sequence number of an existing address; null for a new one. */
        public ?int $id,
        public AddressType $type,
        public bool $default,
        public ?string $name,
        public ?string $country,
        public ?string $city,
        public ?string $postcode,
        public ?string $telephone,
        public ?string $telefax,
        public ?string $email,
        public ?string $field1,
        public ?string $field2,
        public ?string $field3,
        public ?string $field4,
        public ?string $field5,
        public ?string $field6,
    ) {
        if ($id !== null && $id < 1) {
            throw new InvalidArgumentException(sprintf('Address id must be 1 or more, %d given.', $id));
        }

        if ($country !== null && preg_match('~^[A-Z]{2}$~', $country) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a country code: use ISO 3166-1 alpha-2, e.g. "NL".',
                $country,
            ));
        }

        foreach (
            [
                ['address name', $name, 80],
                ['address city', $city, 40],
                ['address postcode', $postcode, 16],
                ['address telephone', $telephone, 25],
                ['address telefax', $telefax, 25],
                ['address email', $email, 200],
                ['address field 1', $field1, 128],
                ['address field 2', $field2, 128],
                ['address field 3', $field3, 128],
                ['address field 4', $field4, 128],
                ['address field 5', $field5, 128],
                ['address field 6', $field6, 128],
            ] as [$field, $value, $max]
        ) {
            if ($value !== null) {
                XmlValue::maxLength($field, $value, $max);
            }
        }
    }

    public static function create(AddressType $type): self
    {
        return new self(
            null,
            $type,
            false,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
    }

    /**
     * A new invoice address, e.g. `AddressInput::invoice('Example B.V.')`.
     */
    public static function invoice(?string $name = null): self
    {
        return self::create(AddressType::Invoice)->name($name);
    }

    /**
     * A new postal address.
     */
    public static function postal(?string $name = null): self
    {
        return self::create(AddressType::Postal)->name($name);
    }

    /**
     * A new contact address.
     */
    public static function contact(?string $name = null): self
    {
        return self::create(AddressType::Contact)->name($name);
    }

    /**
     * Sequence number of the existing address to change; null adds a new address.
     */
    public function id(?int $id): self
    {
        return $this->with(['id' => $id]);
    }

    public function type(AddressType $type): self
    {
        return $this->with(['type' => $type]);
    }

    /**
     * Makes this the default address. `CustomerInput::address()` then unsets the default of
     * the other addresses, as a customer has at most one.
     */
    public function default(bool $default = true): self
    {
        return $this->with(['default' => $default]);
    }

    /**
     * Company name, at most 80 characters.
     */
    public function name(?string $name): self
    {
        return $this->with(['name' => self::nullIfEmpty($name)]);
    }

    /**
     * ISO 3166-1 alpha-2 country code, e.g. `NL`.
     */
    public function country(?string $country): self
    {
        return $this->with(['country' => self::nullIfEmpty($country)]);
    }

    public function city(?string $city): self
    {
        return $this->with(['city' => self::nullIfEmpty($city)]);
    }

    public function postcode(?string $postcode): self
    {
        return $this->with(['postcode' => self::nullIfEmpty($postcode)]);
    }

    public function telephone(?string $telephone): self
    {
        return $this->with(['telephone' => self::nullIfEmpty($telephone)]);
    }

    public function telefax(?string $telefax): self
    {
        return $this->with(['telefax' => self::nullIfEmpty($telefax)]);
    }

    public function email(?string $email): self
    {
        return $this->with(['email' => self::nullIfEmpty($email)]);
    }

    /**
     * Free field 1–6, at most 128 characters. Their labels are set per dimension type; by
     * default 1 is attention of, 2 and 3 are address lines, 4 the VAT number and 5 the
     * chamber of commerce number.
     */
    public function field(int $number, ?string $value): self
    {
        if ($number < 1 || $number > 6) {
            throw new InvalidArgumentException(sprintf('Address field must be 1–6, %d given.', $number));
        }

        return $this->with(['field' . $number => self::nullIfEmpty($value)]);
    }

    /**
     * VAT number (field 4), e.g. `NL001234567B01`. Twinfield refuses an invalid VAT number.
     */
    public function vatNumber(?string $vatNumber): self
    {
        return $this->field(4, $vatNumber);
    }

    /**
     * Chamber of commerce number (field 5), e.g. `12345678`.
     */
    public function chamberOfCommerceNumber(?string $number): self
    {
        return $this->field(5, $number);
    }

    /**
     * Appends the `<address>` element to `<addresses>`.
     */
    public function appendTo(XmlBuilder $xml, DOMElement $addresses): void
    {
        $attributes = ['type' => $this->type->value, 'default' => XmlValue::boolean($this->default)];

        if ($this->id !== null) {
            $attributes = ['id' => (string) $this->id, ...$attributes];
        }

        $address = $xml->add($addresses, 'address', null, $attributes);

        // Empty elements clear a value.
        $xml->add($address, 'name', $this->name);
        $xml->add($address, 'country', $this->country);
        $xml->add($address, 'city', $this->city);
        $xml->add($address, 'postcode', $this->postcode);
        $xml->add($address, 'telephone', $this->telephone);
        $xml->add($address, 'telefax', $this->telefax);
        $xml->add($address, 'email', $this->email);
        $xml->add($address, 'field1', $this->field1);
        $xml->add($address, 'field2', $this->field2);
        $xml->add($address, 'field3', $this->field3);
        $xml->add($address, 'field4', $this->field4);
        $xml->add($address, 'field5', $this->field5);
        $xml->add($address, 'field6', $this->field6);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
