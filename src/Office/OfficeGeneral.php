<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * General settings of an office (`general`): currencies, registration numbers, defaults and
 * address.
 *
 *   echo $office->general->baseCurrency->code, ' ', $office->general->vatNumber, PHP_EOL;
 *
 * Optional settings are null when Twinfield returns an empty element.
 */
final readonly class OfficeGeneral
{
    public function __construct(
        /** Currency of the books, e.g. `EUR`. */
        public NamedReference $baseCurrency,
        /** Currency for reports; null when not set. */
        public ?NamedReference $reportingCurrency,
        public OfficeType $type,
        /** True for a demo office. */
        public bool $demo,
        /** E.g. `NL001234567B01`. */
        public ?string $vatNumber,
        /** VAT number as sole trader (`soletradervatnumber`). */
        public ?string $soleTraderVatNumber,
        /** Chamber of commerce number (`cocnumber`). */
        public ?string $cocNumber,
        /** Empty in all live responses. */
        public ?int $currentYear,
        /** Empty in all live responses. */
        public ?int $currentPeriod,
        /** Empty in all live responses. */
        public ?int $numberOfPeriods,
        /** Last closed financial year; null when no year is closed (Twinfield returns `0`). */
        public ?int $lastClosedYear,
        /** SEPA creditor identifier for direct debits, e.g. `NL00ZZZ123456780000`. */
        public ?string $creditorIdentifier,
        /** Default bank book code, e.g. `BNK`. Its name is empty when the bank book does not exist. */
        public ?NamedReference $defaultBank,
        /** Default matching (`defaultmatching`), e.g. `070`. */
        public ?NamedReference $defaultMatching,
        /** Region code of the office within the organisation. */
        public ?string $region,
        /** Code of the general ledger account hierarchy, e.g. `STANDARD`. */
        public ?string $hierarchy,
        /** True when the office is a template for new offices. */
        public bool $template,
        /** Office this office was created from; null when not set. */
        public ?NamedReference $templateOffice,
        /** True when dimension names may be changed (`editdimensionname`). */
        public bool $editDimensionName,
        /** True when a dimension type may have more than one control account. */
        public bool $allowMultipleControlAccounts,
        public PaymentDiscount $paymentDiscount,
        /** VAT scheme, e.g. `invoice`. */
        public string $scheme,
        /** `allowicmt940`; not documented by Twinfield. */
        public bool $allowIcMt940,
        /** `lockwordinv`; not documented by Twinfield. */
        public bool $lockWordInvoice,
        /** SIC major group code; null when not set (Twinfield returns `0`). */
        public ?string $sicMajorGroup,
        public OfficeAddress $address,
    ) {
        $positive = [
            'current year' => $currentYear,
            'current period' => $currentPeriod,
            'number of periods' => $numberOfPeriods,
            'last closed year' => $lastClosedYear,
        ];

        foreach ($positive as $field => $value) {
            if ($value !== null && $value < 1) {
                throw new InvalidArgumentException(sprintf('Office %s must be 1 or more, %d given.', $field, $value));
            }
        }

        if ($scheme === '') {
            throw new InvalidArgumentException('Office VAT scheme must not be empty.');
        }
    }

    public static function fromXml(XmlElement $general): self
    {
        $lastClosedYear = $general->int('lastclosedyear');
        $sicMajorGroup = $general->string('sicmajorgroup');

        return new self(
            baseCurrency: NamedReference::fromXml($general->child('basecurrency')),
            reportingCurrency: NamedReference::nullable($general->child('reportingcurrency')),
            type: $general->enum('type', OfficeType::class),
            demo: $general->bool('demo'),
            vatNumber: $general->nullableString('vatnumber'),
            soleTraderVatNumber: $general->nullableString('soletradervatnumber'),
            cocNumber: $general->nullableString('cocnumber'),
            currentYear: self::nullableInt($general, 'currentyear'),
            currentPeriod: self::nullableInt($general, 'currentperiod'),
            numberOfPeriods: self::nullableInt($general, 'numberofperiods'),
            lastClosedYear: $lastClosedYear === 0 ? null : $lastClosedYear,
            creditorIdentifier: $general->nullableString('creditoridentifier'),
            defaultBank: NamedReference::nullable($general->child('defaultbank')),
            defaultMatching: NamedReference::nullable($general->child('defaultmatching')),
            region: $general->nullableString('region'),
            hierarchy: $general->nullableString('hierarchy'),
            template: $general->bool('template'),
            templateOffice: NamedReference::nullable($general->child('templateoffice')),
            editDimensionName: $general->bool('editdimensionname'),
            allowMultipleControlAccounts: $general->bool('allowmultiplecontrolaccounts'),
            paymentDiscount: $general->enum('paymentdiscount', PaymentDiscount::class),
            scheme: $general->string('scheme'),
            allowIcMt940: $general->bool('allowicmt940'),
            lockWordInvoice: $general->bool('lockwordinv'),
            sicMajorGroup: $sicMajorGroup === '0' ? null : $sicMajorGroup,
            address: OfficeAddress::fromXml($general->child('address')),
        );
    }

    private static function nullableInt(XmlElement $parent, string $name): ?int
    {
        return $parent->child($name)->text() === '' ? null : $parent->int($name);
    }
}
