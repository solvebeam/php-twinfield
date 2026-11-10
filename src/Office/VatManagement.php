<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * VAT settings of the office (`vatmanagement`): the contacts and periods of the VAT and ICP
 * declarations, the fiscal unity (tax group) and what the declarations include.
 *
 *   echo $office->vatManagement->taxpayer->name, ' ', $office->vatManagement->taxGroup?->code, PHP_EOL;
 *
 * Twinfield does not document the values of the timeframes. In live responses the declaration
 * and ICP timeframes are `0`, `1` (most offices) or `2`, and the starting quarter is `21` or `30`.
 */
final readonly class VatManagement
{
    public function __construct(
        /** The taxpayer (`txp`): the office itself. */
        public VatContact $taxpayer,
        /** The intermediary (`int`) that files the declarations, e.g. the accountant. */
        public VatContact $intermediary,
        /** The `seb` contact; not documented by Twinfield and empty in live responses. */
        public VatContact $seb,
        /** The fiscal unity the office is part of; null when it is in none. */
        public ?NamedReference $taxGroup,
        /** VAT declaration period (`decltimeframe`). */
        public int $declarationTimeframe,
        /** Starting quarter of the VAT declarations (`startingquarter`). */
        public int $startingQuarter,
        /** ICP declaration period (`icptimeframe`). */
        public int $icpTimeframe,
        /** True when the declarations may be estimated (`mayestimate`). */
        public bool $mayEstimate,
        /** Include provisional purchase and sales transactions in the declaration. */
        public bool $includeProvisionalPurchaseOrSalesTransactions,
        /** Include provisional cash and bank transactions in the declaration. */
        public bool $includeProvisionalCashOrBankTransactions,
        /** Include provisional journal transactions in the declaration. */
        public bool $includeProvisionalJournalTransactions,
        /** Carry over the VAT balance to the next declaration (`vatrollover`). */
        public bool $vatRollover,
        /** First period of the fiscal year for the declarations (`fiscalyeartimeframestart`), e.g. `1`. */
        public int $fiscalYearTimeframeStart,
        /** Default gateway for filing (`defgwytype`), e.g. `DIGIPOORT`; null when not set. */
        public ?string $defaultGatewayType,
        /** Default gateway certificate (`defgwycode`); null when not set. */
        public ?string $defaultGatewayCode,
        /** E.g. `invoicebased`: VAT is due on invoicing (`accountingscheme`). */
        public string $accountingScheme,
    ) {
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $vat): self
    {
        return new self(
            taxpayer: VatContact::fromXml($vat->child('txp'), hasSoleTraderId: true),
            intermediary: VatContact::fromXml($vat->child('int')),
            seb: VatContact::fromXml($vat->child('seb')),
            // Only a name attribute: <taxgroup name="Fiscal unity Example">013</taxgroup>.
            taxGroup: NamedReference::nullable($vat->child('taxgroup'), hasShortName: false),
            declarationTimeframe: $vat->int('decltimeframe'),
            startingQuarter: $vat->int('startingquarter'),
            icpTimeframe: $vat->int('icptimeframe'),
            mayEstimate: self::zeroOne($vat->child('mayestimate')),
            includeProvisionalPurchaseOrSalesTransactions: self::capitalisedBool(
                $vat->child('includepurchaseorsalesprovisionaltransactions'),
            ),
            includeProvisionalCashOrBankTransactions: self::capitalisedBool(
                $vat->child('includecashorbankprovisionaltransactions'),
            ),
            includeProvisionalJournalTransactions: self::capitalisedBool(
                $vat->child('includejournalprovisionaltransactions'),
            ),
            vatRollover: self::capitalisedBool($vat->child('vatrollover')),
            fiscalYearTimeframeStart: $vat->int('fiscalyeartimeframestart'),
            defaultGatewayType: $vat->nullableString('defgwytype'),
            defaultGatewayCode: $vat->nullableString('defgwycode'),
            accountingScheme: $vat->string('accountingscheme'),
        );
    }

    /**
     * Unlike the rest of the response, these booleans are written as `True` and `False`.
     */
    private static function capitalisedBool(XmlElement $element): bool
    {
        return match ($element->text()) {
            'True' => true,
            'False' => false,
            default => throw $element->invalid('True or False'),
        };
    }

    private static function zeroOne(XmlElement $element): bool
    {
        return match ($element->text()) {
            '1' => true,
            '0' => false,
            default => throw $element->invalid('0 or 1'),
        };
    }
}
