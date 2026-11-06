<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankBook;

use SolveBeam\Twinfield\CommandQuery\ResultElement;

/**
 * The bank account of a bank book, as Twinfield returns it. Every field is optional in
 * Twinfield (an empty element), so each one is null when not set.
 *
 * To change it, call edit() and pass the result to BankBookInput::bankAccount().
 */
final readonly class BankAccount
{
    public function __construct(
        /** Account holder, e.g. `Example B.V.`. */
        public ?string $name,
        /** Account number, e.g. `3255112`. The default bank book of a new company has `.`. */
        public ?string $number,
        /** E.g. `NL45INGB0003255112`. */
        public ?string $iban,
        /** E.g. `INGBNL2A`. */
        public ?string $bic,
        /** National bank identifier code. */
        public ?string $nbic,
        /** ISO 3166-1 alpha-2 country code, e.g. `NL`. */
        public ?string $country,
        /** Reference number of the bank. */
        public ?string $referenceNumber,
    ) {
    }

    /**
     * From the `BankAccount` element of a GetBankBook result.
     */
    public static function fromResult(ResultElement $element): self
    {
        // Empty elements, e.g. <b:Bic/>, when a value is not set.
        return new self(
            name: $element->nullableString('Name'),
            number: $element->nullableString('Number'),
            iban: $element->nullableString('Iban'),
            bic: $element->nullableString('Bic'),
            nbic: $element->nullableString('Nbic'),
            country: $element->nullableString('Country'),
            referenceNumber: $element->nullableString('ReferenceNumber'),
        );
    }

    /**
     * Input with all values of this account, to change and pass to BankBookInput::bankAccount().
     */
    public function edit(): BankAccountInput
    {
        return BankAccountInput::create()
            ->name($this->name)
            ->number($this->number)
            ->iban($this->iban)
            ->bic($this->bic)
            ->nbic($this->nbic)
            ->country($this->country)
            ->referenceNumber($this->referenceNumber);
    }
}
