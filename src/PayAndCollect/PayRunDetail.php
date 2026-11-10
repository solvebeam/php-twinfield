<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use DateTimeImmutable;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * An invoice in a pay run summary (`DetailInfo`): the transaction line that is paid or collected.
 */
final readonly class PayRunDetail
{
    public function __construct(
        /** Identifier within the summary; use it with PayRunModification. */
        public int $id,
        /** Day book (transaction type) code of the invoice, e.g. `INK`. */
        public string $transaction,
        /** Name of the day book. */
        public string $transactionName,
        public int $transactionNumber,
        public int $transactionLine,
        public string $invoiceNumber,
        public DateTimeImmutable $invoiceDate,
        public DateTimeImmutable $invoiceDueDate,
        /** Amount to pay or collect, in the currency of the summary; can be changed with modify(). */
        public Amount $value,
        /** The amount before it was changed. */
        public Amount $originalValue,
        /** Value in the base currency of the company. */
        public Amount $baseValue,
        public Amount $originalBaseValue,
        /** Not on the documentation page; in the WSDL. Can be changed with modify(). */
        public Amount $discountAmount,
        /** False when the invoice is excluded from the run. */
        public bool $included,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResult(ResultElement $detail): self
    {
        return new self(
            id: $detail->int('Id'),
            transaction: PayAndCollectXml::string($detail, 'Transaction'),
            transactionName: PayAndCollectXml::string($detail, 'TransactionName'),
            transactionNumber: $detail->int('TransactionNumber'),
            transactionLine: $detail->int('TransactionLine'),
            invoiceNumber: PayAndCollectXml::string($detail, 'InvoiceNumber'),
            invoiceDate: PayAndCollectXml::date($detail, 'InvoiceDate'),
            invoiceDueDate: PayAndCollectXml::date($detail, 'InvoiceDueDate'),
            value: $detail->amount('Value'),
            originalValue: $detail->amount('OriginalValue'),
            baseValue: $detail->amount('BaseValue'),
            originalBaseValue: $detail->amount('OriginalBaseValue'),
            discountAmount: $detail->amount('DiscountAmount'),
            included: $detail->bool('Included'),
        );
    }
}
