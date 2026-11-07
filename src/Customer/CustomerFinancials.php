<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Financial settings of a customer (`financials`): payment term, direct debit, e-invoicing,
 * balance sheet account and VAT code.
 */
final readonly class CustomerFinancials
{
    public function __construct(
        /** Payment term in days, e.g. `30`. */
        public int $dueDays,
        /** True when invoices are collected by direct debit. */
        public bool $directDebit,
        public MeansOfPayment $meansOfPayment,
        /** Payment type code for direct debit, e.g. `SEPANLDD`; null when not set. */
        public ?string $payCode,
        /** True when invoices are sent by email. */
        public bool $eInvoicing,
        /** Email address for invoices, at most 200 characters; null when not set. */
        public ?string $eInvoicingEmail,
        /** Balance sheet account of the customer, e.g. `1300`. */
        public string $balanceAccount,
        /** Default VAT code, e.g. `VH`; null when not set. */
        public ?string $vatCode,
        /** SEPA mandate; null when not set. */
        public ?CollectMandate $collectMandate,
        /** SEPA scheme; null when not set. */
        public ?CollectionSchema $collectionSchema,
    ) {
    }

    /**
     * From the `<financials>` element of a customer.
     */
    public static function fromXml(XmlElement $financials): self
    {
        // Optional in Twinfield: empty elements, e.g. <paycode name="" shortname=""/>, when not set.
        return new self(
            dueDays: $financials->int('duedays'),
            directDebit: $financials->bool('payavailable'),
            meansOfPayment: $financials->enum('meansofpayment', MeansOfPayment::class),
            payCode: $financials->nullableString('paycode'),
            eInvoicing: $financials->bool('ebilling'),
            eInvoicingEmail: $financials->nullableString('ebillmail'),
            balanceAccount: $financials->string('substitutewith'),
            vatCode: $financials->nullableString('vatcode'),
            collectMandate: CollectMandate::fromXml($financials->child('collectmandate')),
            collectionSchema: $financials->nullableEnum('collectionschema', CollectionSchema::class),
        );
    }
}
