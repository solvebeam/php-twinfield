<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankBook;

use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\Nil;

/**
 * The bank account of a bank book to create or change. Immutable: every method returns a new input.
 *
 *   $account = BankAccountInput::create()
 *       ->name('Example B.V.')
 *       ->iban('NL45 INGB 0003 2551 12')
 *       ->bic('INGBNL2A')
 *       ->country('NL');
 *
 * The IBAN is stored without spaces in capitals and must have a valid check digit. For an
 * existing account, start from `$bankBook->bankAccount->edit()`, so all current values are kept.
 */
final readonly class BankAccountInput
{
    private function __construct(
        public ?string $name,
        public ?string $number,
        public ?string $iban,
        public ?string $bic,
        public ?string $nbic,
        public ?string $country,
        public ?string $referenceNumber,
    ) {
        if ($iban !== null && !self::isValidIban($iban)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid IBAN.', $iban));
        }

        if ($bic !== null && preg_match('~^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}(?:[A-Z0-9]{3})?$~', $bic) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a BIC: 8 or 11 letters and digits.', $bic));
        }

        if ($country !== null && preg_match('~^[A-Z]{2}$~', $country) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a country code such as NL.', $country));
        }
    }

    /**
     * An empty account; set at least a number or IBAN.
     */
    public static function create(): self
    {
        return new self(null, null, null, null, null, null, null);
    }

    /**
     * Account holder; null removes it.
     */
    public function name(?string $name): self
    {
        return $this->with(['name' => self::nullIfEmpty($name)]);
    }

    /**
     * Account number; null removes it.
     */
    public function number(?string $number): self
    {
        return $this->with(['number' => self::nullIfEmpty($number)]);
    }

    /**
     * E.g. `NL45INGB0003255112`; spaces are removed. Null removes it.
     */
    public function iban(?string $iban): self
    {
        $iban = $iban === null ? null : strtoupper(str_replace(' ', '', $iban));

        return $this->with(['iban' => self::nullIfEmpty($iban)]);
    }

    /**
     * E.g. `INGBNL2A`; null removes it.
     */
    public function bic(?string $bic): self
    {
        return $this->with(['bic' => self::nullIfEmpty($bic === null ? null : strtoupper($bic))]);
    }

    /**
     * National bank identifier code; null removes it.
     */
    public function nbic(?string $nbic): self
    {
        return $this->with(['nbic' => self::nullIfEmpty($nbic)]);
    }

    /**
     * ISO 3166-1 alpha-2 country code, e.g. `NL`; null removes it.
     */
    public function country(?string $country): self
    {
        return $this->with(['country' => self::nullIfEmpty($country === null ? null : strtoupper($country))]);
    }

    /**
     * Reference number of the bank; null removes it.
     */
    public function referenceNumber(?string $referenceNumber): self
    {
        return $this->with(['referenceNumber' => self::nullIfEmpty($referenceNumber)]);
    }

    public function isEmpty(): bool
    {
        return $this == self::create();
    }

    /**
     * The `BankAccount` fields of a command, in WSDL order. All fields but Country are required
     * (nillable) in the WSDL, so empty ones are sent as nil instead of being left out.
     *
     * @return array<string, string|Nil|null>
     *
     * @internal
     */
    public function toFields(): array
    {
        return [
            'Bic' => $this->bic ?? Nil::Value,
            'Country' => $this->country,
            'Iban' => $this->iban ?? Nil::Value,
            'Name' => $this->name ?? Nil::Value,
            'Nbic' => $this->nbic ?? Nil::Value,
            'Number' => $this->number ?? Nil::Value,
            'ReferenceNumber' => $this->referenceNumber ?? Nil::Value,
        ];
    }

    /**
     * ISO 13616: 2 letters, 2 check digits, up to 30 letters and digits; mod 97 of the rearranged number is 1.
     */
    private static function isValidIban(string $iban): bool
    {
        if (preg_match('~^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$~', $iban) !== 1) {
            return false;
        }

        $digits = '';

        foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $character) {
            $digits .= ctype_digit($character) ? $character : (string) (ord($character) - 55);
        }

        $remainder = 0;

        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder . $chunk) % 97;
        }

        return $remainder === 1;
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @param array{
     *     name?: ?string,
     *     number?: ?string,
     *     iban?: ?string,
     *     bic?: ?string,
     *     nbic?: ?string,
     *     country?: ?string,
     *     referenceNumber?: ?string,
     * } $changes
     */
    private function with(array $changes): self
    {
        $values = $changes + [
            'name' => $this->name,
            'number' => $this->number,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'nbic' => $this->nbic,
            'country' => $this->country,
            'referenceNumber' => $this->referenceNumber,
        ];

        return new self(
            $values['name'],
            $values['number'],
            $values['iban'],
            $values['bic'],
            $values['nbic'],
            $values['country'],
            $values['referenceNumber'],
        );
    }
}
