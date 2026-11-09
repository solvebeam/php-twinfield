<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * An account of a VAT percentage to create or update. Immutable: every method returns a new
 * input.
 *
 *   VatAccountInput::vat('1530')->group('NL', 'NL1A');               // all VAT on 1530, box 1a
 *
 *   // Reverse charge: report the VAT as payable and as deductible at the same time.
 *   VatAccountInput::vat('1530', '-100')->group('NL', 'NL4B');
 *   VatAccountInput::vat('1520')->group('NL', 'NL5B');
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/vat/
 */
final readonly class VatAccountInput
{
    private function __construct(
        public VatAccountLineType $lineType,
        /** General ledger account (`dim1`) the VAT is posted on, e.g. `1530`. */
        public string $generalLedgerAccount,
        /** Share of the VAT posted on this account; negative for reverse charge. */
        public Percentage $percentage,
        /** Country of the VAT group, e.g. `NL`; null without VAT group. */
        public ?string $groupCountry,
        /** VAT group (box of the VAT return), e.g. `NL1A`; null without VAT group. */
        public ?string $group,
    ) {
        XmlValue::code($generalLedgerAccount);
        self::validateShare($percentage);

        if (($groupCountry === null) !== ($group === null)) {
            throw new InvalidArgumentException('A VAT group needs both a country and a group code.');
        }

        if ($groupCountry !== null && $group !== null) {
            XmlValue::code($groupCountry);
            XmlValue::code($group);
        }
    }

    /**
     * @param string $generalLedgerAccount General ledger account the VAT is posted on, e.g. `1530`.
     * @param Percentage|numeric-string $percentage Share of the VAT, e.g. `'100'` (default) or
     *        `'-100'` for reverse charge.
     */
    public static function vat(string $generalLedgerAccount, Percentage|string $percentage = '100'): self
    {
        return self::create(VatAccountLineType::Vat, $generalLedgerAccount, $percentage);
    }

    /**
     * A detail line: this share of the calculated VAT is posted on another general ledger
     * account, e.g. the part of the VAT on a car that is not deductible.
     *
     * @param Percentage|numeric-string $percentage Share of the VAT, e.g. `'30'`.
     */
    public static function detail(string $generalLedgerAccount, Percentage|string $percentage): self
    {
        return self::create(VatAccountLineType::Detail, $generalLedgerAccount, $percentage);
    }

    /**
     * @param Percentage|numeric-string $percentage
     */
    public static function create(
        VatAccountLineType $lineType,
        string $generalLedgerAccount,
        Percentage|string $percentage,
    ): self {
        return new self($lineType, $generalLedgerAccount, Percentage::of($percentage), null, null);
    }

    /**
     * The VAT group: the box of the VAT return the amount is reported in, e.g. `group('NL', 'NL1A')`.
     */
    public function group(string $country, string $group): self
    {
        return new self($this->lineType, $this->generalLedgerAccount, $this->percentage, $country, $group);
    }

    public function withoutGroup(): self
    {
        return new self($this->lineType, $this->generalLedgerAccount, $this->percentage, null, null);
    }

    /**
     * @param Percentage|numeric-string $percentage Share of the VAT posted on this account.
     */
    public function percentage(Percentage|string $percentage): self
    {
        return new self(
            $this->lineType,
            $this->generalLedgerAccount,
            Percentage::of($percentage),
            $this->groupCountry,
            $this->group,
        );
    }

    /**
     * Adds the `<account>` element. The line id is read only, so it is not sent.
     *
     * @internal
     */
    public function appendTo(XmlBuilder $xml, DOMElement $accounts): void
    {
        $account = $xml->add($accounts, 'account');
        $xml->add($account, 'dim1', $this->generalLedgerAccount);

        if ($this->groupCountry !== null) {
            $xml->add($account, 'groupcountry', $this->groupCountry);
            $xml->add($account, 'group', $this->group);
        }

        $xml->add($account, 'percentage', $this->percentage->value);
        $xml->add($account, 'linetype', $this->lineType->value);
    }

    /**
     * A share is -100 to 100 percent of the VAT.
     *
     * @internal
     */
    public static function validateShare(Percentage $percentage): void
    {
        if ($percentage->compare('-100') < 0 || $percentage->compare('100') > 0) {
            throw new InvalidArgumentException(sprintf(
                'The share of a VAT account must be -100 to 100 percent, %s given.',
                $percentage,
            ));
        }
    }
}
