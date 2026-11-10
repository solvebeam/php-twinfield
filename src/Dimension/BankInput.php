<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A bank account of a customer or supplier to create or update. Immutable: every method
 * returns a new input.
 *
 *   $bank = BankInput::create()
 *       ->iban('NL91 ABNA 0417 1643 00')
 *       ->bic('ABNANL2A')
 *       ->ascription('Example B.V.')
 *       ->default();
 *
 * For an existing bank account, start from `$bank->edit()`: it keeps the id, so Twinfield
 * changes that account instead of adding one. A value you set to null is cleared.
 */
final readonly class BankInput
{
    private function __construct(
        /** Sequence number of an existing bank account; null for a new one. */
        public ?int $id,
        public bool $default,
        public ?string $ascription,
        public ?string $accountNumber,
        public ?string $iban,
        public ?string $bic,
        public ?string $nationalBankCode,
        public ?string $bankName,
        public ?string $bankAddress,
        public ?string $bankAddressNumber,
        public ?string $postcode,
        public ?string $city,
        public ?string $state,
        public ?string $country,
        /** Suppliers, payment type BTL91 only. */
        public ?WayOfPayment $wayOfPayment = null,
        /** Suppliers, with WayOfPayment::Cheque only. */
        public ?PayAddress $payAddress = null,
    ) {
        if ($id !== null && $id < 1) {
            throw new InvalidArgumentException(sprintf('Bank id must be 1 or more, %d given.', $id));
        }

        if ($country !== null && preg_match('~^[A-Z]{2}$~', $country) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a country code: use ISO 3166-1 alpha-2, e.g. "NL".',
                $country,
            ));
        }

        if ($payAddress !== null && $wayOfPayment !== WayOfPayment::Cheque) {
            throw new InvalidArgumentException('A pay address is only for payment by cheque (WayOfPayment::Cheque).');
        }

        foreach (
            [
                ['bank ascription', $ascription, 40],
                ['bank account number', $accountNumber, 40],
                ['IBAN', $iban, 40],
                ['BIC', $bic, 16],
                ['national bank code', $nationalBankCode, 20],
                ['bank name', $bankName, 40],
                ['bank address', $bankAddress, 128],
                ['bank address number', $bankAddressNumber, 128],
                ['bank postcode', $postcode, 16],
                ['bank city', $city, 40],
                ['bank state', $state, 40],
            ] as [$field, $value, $max]
        ) {
            if ($value !== null) {
                XmlValue::maxLength($field, $value, $max);
            }
        }
    }

    public static function create(): self
    {
        return new self(null, false, null, null, null, null, null, null, null, null, null, null, null, null);
    }

    /**
     * Sequence number of the existing bank account to change; null adds a new account.
     */
    public function id(?int $id): self
    {
        return $this->with(['id' => $id]);
    }

    /**
     * Makes this the default bank account. `bank()` of the customer or supplier input then
     * unsets the default of the other accounts, as there is at most one.
     */
    public function default(bool $default = true): self
    {
        return $this->with(['default' => $default]);
    }

    /**
     * Account holder, at most 40 characters.
     */
    public function ascription(?string $ascription): self
    {
        return $this->with(['ascription' => self::nullIfEmpty($ascription)]);
    }

    public function accountNumber(?string $accountNumber): self
    {
        return $this->with(['accountNumber' => self::nullIfEmpty($accountNumber)]);
    }

    /**
     * IBAN, e.g. `NL91 ABNA 0417 1643 00`. Spaces are removed and letters made upper case.
     *
     * @throws InvalidArgumentException When it is not a valid IBAN (format or checksum).
     */
    public function iban(?string $iban): self
    {
        if ($iban === null || $iban === '') {
            return $this->with(['iban' => null]);
        }

        $iban = strtoupper(str_replace(' ', '', $iban));

        if (!self::isValidIban($iban)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid IBAN.', $iban));
        }

        return $this->with(['iban' => $iban]);
    }

    /**
     * BIC, e.g. `ABNANL2A`.
     */
    public function bic(?string $bic): self
    {
        return $this->with(['bic' => $bic === null || $bic === '' ? null : strtoupper($bic)]);
    }

    public function nationalBankCode(?string $code): self
    {
        return $this->with(['nationalBankCode' => self::nullIfEmpty($code)]);
    }

    public function bankName(?string $bankName): self
    {
        return $this->with(['bankName' => self::nullIfEmpty($bankName)]);
    }

    /**
     * Address of the bank, and its house number.
     */
    public function bankAddress(?string $address, ?string $number = null): self
    {
        return $this->with([
            'bankAddress' => self::nullIfEmpty($address),
            'bankAddressNumber' => self::nullIfEmpty($number),
        ]);
    }

    public function postcode(?string $postcode): self
    {
        return $this->with(['postcode' => self::nullIfEmpty($postcode)]);
    }

    public function city(?string $city): self
    {
        return $this->with(['city' => self::nullIfEmpty($city)]);
    }

    public function state(?string $state): self
    {
        return $this->with(['state' => self::nullIfEmpty($state)]);
    }

    /**
     * ISO 3166-1 alpha-2 country code of the bank, e.g. `NL`.
     */
    public function country(?string $country): self
    {
        return $this->with(['country' => self::nullIfEmpty($country)]);
    }

    /**
     * How a supplier is paid to this account, and for a cheque where it is sent. Only for the
     * payment type BTL91; null sends nothing.
     *
     *   ->wayOfPayment(WayOfPayment::Cheque, PayAddress::Beneficiary)
     *
     * @throws InvalidArgumentException For a pay address without WayOfPayment::Cheque.
     */
    public function wayOfPayment(?WayOfPayment $wayOfPayment, ?PayAddress $payAddress = null): self
    {
        return $this->with(['wayOfPayment' => $wayOfPayment, 'payAddress' => $payAddress]);
    }

    /**
     * Appends the `<bank>` element to `<banks>`.
     *
     * @internal
     */
    public function appendTo(XmlBuilder $xml, DOMElement $banks): void
    {
        $attributes = ['default' => XmlValue::boolean($this->default)];

        if ($this->id !== null) {
            $attributes = ['id' => (string) $this->id, ...$attributes];
        }

        $bank = $xml->add($banks, 'bank', null, $attributes);

        // Empty elements clear a value.
        $xml->add($bank, 'ascription', $this->ascription);
        $xml->add($bank, 'accountnumber', $this->accountNumber);

        $address = $xml->add($bank, 'address');
        $xml->add($address, 'field2', $this->bankAddress);
        $xml->add($address, 'field3', $this->bankAddressNumber);

        $xml->add($bank, 'bankname', $this->bankName);
        $xml->add($bank, 'biccode', $this->bic);
        $xml->add($bank, 'city', $this->city);
        $xml->add($bank, 'country', $this->country);
        $xml->add($bank, 'iban', $this->iban);
        $xml->add($bank, 'natbiccode', $this->nationalBankCode);
        $xml->add($bank, 'postcode', $this->postcode);
        $xml->add($bank, 'state', $this->state);

        // Only sent when set: these are for suppliers with payment type BTL91.
        if ($this->wayOfPayment !== null) {
            $xml->add($bank, 'wayofpayment', $this->wayOfPayment->value);
        }

        if ($this->payAddress !== null) {
            $xml->add($bank, 'payaddress', $this->payAddress->value);
        }
    }

    /**
     * The IBAN as Twinfield has it, without the checksum check of iban(). For Bank::edit().
     *
     * @internal
     */
    public function withStoredIban(?string $iban): self
    {
        return $this->with(['iban' => self::nullIfEmpty($iban)]);
    }

    /**
     * ISO 13616: country code, two check digits, up to 30 letters and digits, and mod 97 = 1.
     */
    public static function isValidIban(string $iban): bool
    {
        if (preg_match('~^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$~', $iban) !== 1) {
            return false;
        }

        $digits = '';

        foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $character) {
            $digits .= ctype_digit($character) ? $character : (string) (ord($character) - 55);
        }

        return bcmod($digits, '97') === '1';
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
