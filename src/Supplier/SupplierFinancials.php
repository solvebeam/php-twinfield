<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Supplier;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Financial settings of a supplier (`financials`): payment term, how the supplier is paid,
 * balance sheet account, external supplier number and VAT code.
 *
 * Twinfield also returns elements that are not in use for suppliers (`ebilling`,
 * `collectmandate`, `collectionschema`, `vattype`, …); they are left out.
 */
final readonly class SupplierFinancials
{
    public function __construct(
        /** Payment term in days, e.g. `30`. */
        public int $dueDays,
        /** True when Twinfield pays the supplier (`payavailable`): by payment file or cheque. */
        public bool $payAvailable,
        public MeansOfPayment $meansOfPayment,
        /** Payment type code for a payment file, e.g. `SEPANLCT`; null when not set. */
        public ?string $payCode,
        /** Balance sheet account of the supplier, e.g. `1600`. */
        public string $balanceAccount,
        /** External supplier number (`relationsreference`), at most 40 characters; null when not set. */
        public ?string $relationsReference,
        /** Default VAT code, e.g. `IH`; `#` for no VAT; null when not set. */
        public ?string $vatCode,
    ) {
    }

    /**
     * From the `<financials>` element of a supplier.
     */
    public static function fromXml(XmlElement $financials): self
    {
        // Optional in Twinfield: empty elements, e.g. <paycode name="" shortname=""/>, when not set.
        return new self(
            dueDays: $financials->int('duedays'),
            payAvailable: $financials->bool('payavailable'),
            meansOfPayment: $financials->enum('meansofpayment', MeansOfPayment::class),
            payCode: $financials->nullableString('paycode'),
            balanceAccount: $financials->string('substitutewith'),
            relationsReference: $financials->nullableString('relationsreference'),
            vatCode: $financials->nullableString('vatcode'),
        );
    }
}
