<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use SolveBeam\Twinfield\CommandQuery\ResultElement;

/**
 * Bank account of the relation of a pay run summary (`Bank`). Every field is optional: null when
 * Twinfield leaves it out or empty, as for a bank account with only an IBAN.
 */
final readonly class PayRunBank
{
    public function __construct(
        /** Name of the account holder. */
        public ?string $ascription,
        public ?string $accountNumber,
        public ?string $iban,
        /** National code of the bank. */
        public ?string $nationalCode,
        /** BIC. */
        public ?string $internationalCode,
        public ?string $address,
        public ?string $postalCode,
        /** Not in use according to the documentation; the manual calls it the city of the bank. */
        public ?string $branch,
        public ?string $country,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResult(ResultElement $bank): self
    {
        return new self(
            ascription: self::optional($bank, 'Ascription'),
            accountNumber: self::optional($bank, 'AccountNumber'),
            iban: self::optional($bank, 'Iban'),
            nationalCode: self::optional($bank, 'NationalCode'),
            internationalCode: self::optional($bank, 'InternationalCode'),
            address: self::optional($bank, 'Address'),
            postalCode: self::optional($bank, 'PostalCode'),
            branch: self::optional($bank, 'Branch'),
            country: self::optional($bank, 'Country'),
        );
    }

    private static function optional(ResultElement $bank, string $name): ?string
    {
        // minOccurs="0" in the WSDL: left out, nil and empty all mean no value.
        return $bank->nullableChild($name) === null ? null : $bank->nullableString($name);
    }
}
