<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Period\Period;

/**
 * Shortcuts to the addresses and bank accounts of a customer (Customer) or supplier
 * (Supplier). The class has the properties `addresses` (list of Address) and `banks` (list of
 * Bank).
 *
 * @internal
 */
trait RelationShortcuts
{
    /**
     * The default address, or the first one; null when there are no addresses.
     */
    public function defaultAddress(): ?Address
    {
        foreach ($this->addresses as $address) {
            if ($address->default) {
                return $address;
            }
        }

        return $this->addresses[0] ?? null;
    }

    /**
     * The address with this id, or null.
     */
    public function address(int $id): ?Address
    {
        foreach ($this->addresses as $address) {
            if ($address->id === $id) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Addresses of one type, e.g. `addressesOfType(AddressType::Postal)`.
     *
     * @return list<Address>
     */
    public function addressesOfType(AddressType $type): array
    {
        return array_values(array_filter(
            $this->addresses,
            static fn (Address $address): bool => $address->type === $type,
        ));
    }

    /**
     * The default bank account, or the first one; null when there are no bank accounts.
     */
    public function defaultBank(): ?Bank
    {
        foreach ($this->banks as $bank) {
            if ($bank->default) {
                return $bank;
            }
        }

        return $this->banks[0] ?? null;
    }

    /**
     * The bank account with this id, or null.
     */
    public function bank(int $id): ?Bank
    {
        foreach ($this->banks as $bank) {
            if ($bank->id === $id) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * VAT number of the default address (field 4).
     */
    public function vatNumber(): ?string
    {
        return $this->defaultAddress()?->vatNumber();
    }

    /**
     * Chamber of commerce number of the default address (field 5).
     */
    public function chamberOfCommerceNumber(): ?string
    {
        return $this->defaultAddress()?->chamberOfCommerceNumber();
    }

    /**
     * A period from a year and period element; Twinfield uses 0 for "no limit".
     */
    private static function period(XmlElement $dimension, string $yearElement, string $periodElement): ?Period
    {
        $year = $dimension->int($yearElement);
        $period = $dimension->int($periodElement);

        return $year === 0 ? null : Period::of($year, $period);
    }
}
