<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A bank account of a customer or supplier, as Twinfield returns it.
 *
 * Every field is optional in Twinfield (an empty element), so each one is null when not set.
 *
 * To change it, call edit() and pass the result to `CustomerInput::bank()` or
 * `SupplierInput::bank()`.
 */
final readonly class Bank
{
    public function __construct(
        /** Sequence number within the customer or supplier, e.g. `1`. */
        public int $id,
        /** True for the default bank account; a customer or supplier has at most one. */
        public bool $default,
        /**
         * True when the bank account is blocked. Read-only; null when Twinfield leaves it out:
         * it is not in the documented responses, but in every live one.
         */
        public ?bool $blocked,
        /** Account holder, e.g. `Example B.V.`. */
        public ?string $ascription,
        /** Account number, e.g. `417164300`. */
        public ?string $accountNumber,
        /** E.g. `NL91ABNA0417164300`. */
        public ?string $iban,
        /** E.g. `ABNANL2A`. */
        public ?string $bic,
        /** National bank code. */
        public ?string $nationalBankCode,
        /** E.g. `ABN AMRO`. */
        public ?string $bankName,
        /** Address of the bank. */
        public ?string $bankAddress,
        /** House number of the bank address. */
        public ?string $bankAddressNumber,
        public ?string $postcode,
        public ?string $city,
        public ?string $state,
        /** ISO 3166-1 alpha-2 country code, e.g. `NL`. */
        public ?string $country,
        /** Suppliers, payment type BTL91 only; null when not set. */
        public ?WayOfPayment $wayOfPayment = null,
        /** Suppliers, with WayOfPayment::Cheque only; null when not set. */
        public ?PayAddress $payAddress = null,
    ) {
        if ($id < 1) {
            throw new InvalidArgumentException(sprintf('Bank id must be 1 or more, %d given.', $id));
        }
    }

    /**
     * From a `<bank>` element of a dimension.
     */
    public static function fromXml(XmlElement $bank): self
    {
        $address = $bank->child('address');

        // Empty elements, e.g. <natbiccode/>, when a value is not set.
        return new self(
            id: $bank->intAttribute('id'),
            default: $bank->boolAttribute('default'),
            blocked: $bank->hasAttribute('blocked') ? $bank->boolAttribute('blocked') : null,
            ascription: $bank->nullableString('ascription'),
            accountNumber: $bank->nullableString('accountnumber'),
            iban: $bank->nullableString('iban'),
            bic: $bank->nullableString('biccode'),
            nationalBankCode: $bank->nullableString('natbiccode'),
            bankName: $bank->nullableString('bankname'),
            bankAddress: $address->nullableString('field2'),
            bankAddressNumber: $address->nullableString('field3'),
            postcode: $bank->nullableString('postcode'),
            city: $bank->nullableString('city'),
            state: $bank->nullableString('state'),
            country: $bank->nullableString('country'),
            // Documented for suppliers, but left out of every live response seen so far.
            wayOfPayment: $bank->nullableChild('wayofpayment') === null
                ? null
                : $bank->nullableEnum('wayofpayment', WayOfPayment::class),
            payAddress: $bank->nullableChild('payaddress') === null
                ? null
                : $bank->nullableEnum('payaddress', PayAddress::class),
        );
    }

    /**
     * Input with all values of this bank account, including its id, to change and pass to
     * `CustomerInput::bank()` or `SupplierInput::bank()`. The read-only `blocked` is not part of it.
     */
    public function edit(): BankInput
    {
        return BankInput::create()
            ->id($this->id)
            ->default($this->default)
            ->ascription($this->ascription)
            ->accountNumber($this->accountNumber)
            // As stored: Twinfield may hold an IBAN that no longer passes the checksum.
            ->withStoredIban($this->iban)
            ->bic($this->bic)
            ->nationalBankCode($this->nationalBankCode)
            ->bankName($this->bankName)
            ->bankAddress($this->bankAddress, $this->bankAddressNumber)
            ->postcode($this->postcode)
            ->city($this->city)
            ->state($this->state)
            ->country($this->country)
            ->wayOfPayment($this->wayOfPayment, $this->payAddress);
    }
}
