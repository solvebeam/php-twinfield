<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A general ledger account or dimension group of an asset method, with the name Twinfield returns.
 */
final readonly class AccountReference
{
    public function __construct(
        /** Account code, e.g. `0501`, or the dimension group code. */
        public string $code,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
        /** Dimension type, e.g. `BAS` or `PNL`; null for a dimension group. */
        public ?string $dimensionType,
    ) {
        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Account reference code and name must not be empty.');
        }

        if ($dimensionType === '') {
            throw new InvalidArgumentException('Account reference dimension type must not be empty; null for a group.');
        }
    }

    /**
     * From an account element such as
     * `<purchasevalue name="Purchase value" shortname="" dimensiontype="BAS">0501</purchasevalue>`.
     *
     * Null for an empty element: the account is not set, which is valid in Twinfield.
     */
    public static function account(XmlElement $element): ?self
    {
        return $element->text() === '' ? null : self::fromXml($element, $element->attribute('dimensiontype'));
    }

    /**
     * From a dimension group element such as `<purchasevaluegroup name="" shortname=""/>`,
     * which has no dimension type. Null when no group is set.
     */
    public static function group(XmlElement $element): ?self
    {
        return $element->text() === '' ? null : self::fromXml($element, null);
    }

    private static function fromXml(XmlElement $element, ?string $dimensionType): self
    {
        foreach (['name' => $element->attribute('name'), 'dimensiontype' => $dimensionType] as $attribute => $value) {
            if ($value === '') {
                throw new UnexpectedResponseException(sprintf('%s/@%s is empty.', $element->path, $attribute));
            }
        }

        $shortName = $element->attribute('shortname');

        return new self(
            $element->text(),
            $element->attribute('name'),
            $shortName === '' ? null : $shortName,
            $dimensionType,
        );
    }
}
