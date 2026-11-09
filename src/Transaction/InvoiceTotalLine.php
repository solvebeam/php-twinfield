<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The total line (`type="total"`) of a sales transaction: what the customer owes, including VAT.
 *
 * Optional values are null when Twinfield leaves them out of the response.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/
 */
final readonly class InvoiceTotalLine
{
    /**
     * @param list<MatchSet> $matches
     */
    public function __construct(
        public int $id,
        /** `dim1`: the accounts receivable balance account, e.g. `1300`. */
        public DimensionReference $account,
        /** `dim2`: the customer. */
        public DimensionReference $customer,
        /** Debit for an invoice, credit for a credit note. */
        public DebitCredit $debitCredit,
        /** Total including VAT. */
        public LineAmounts $amounts,
        public ?string $description,
        public ?Amount $vatTotal,
        public ?Amount $vatBaseTotal,
        public ?Amount $vatRepTotal,
        /** Payment status: `available` while (partly) unpaid, `matched` when paid. */
        public MatchStatus $matchStatus,
        /** Level of the matchable dimension, e.g. 2 for the customer. */
        public ?int $matchLevel,
        public ?int $relation,
        /** Amount still open in the transaction currency. */
        public ?Amount $openValue,
        /** Amount still open in the base currency. */
        public ?Amount $openBaseValue,
        /** Amount still open in the reporting currency. */
        public ?Amount $openRepValue,
        /** `N`: the invoice is left out of the direct debit runs of Twinfield. */
        public ?string $freeChar,
        public ?string $comment,
        /** Payments matched with this invoice; empty when nothing is matched. */
        public array $matches,
    ) {
    }

    public static function fromXml(XmlElement $line): self
    {
        return new self(
            id: (int) $line->attribute('id'),
            account: TransactionXml::dimension($line, 'dim1'),
            customer: TransactionXml::dimension($line, 'dim2'),
            debitCredit: $line->enum('debitcredit', DebitCredit::class),
            amounts: LineAmounts::fromXml($line),
            description: TransactionXml::optionalString($line, 'description'),
            vatTotal: TransactionXml::optionalAmount($line, 'vattotal'),
            vatBaseTotal: TransactionXml::optionalAmount($line, 'vatbasetotal'),
            vatRepTotal: TransactionXml::optionalAmount($line, 'vatreptotal'),
            matchStatus: $line->enum('matchstatus', MatchStatus::class),
            matchLevel: TransactionXml::optionalInt($line, 'matchlevel'),
            relation: TransactionXml::optionalInt($line, 'relation'),
            // Live responses only have basevalueopen. The reference table names valueopen,
            // basevalueopen and repvalueopen, the response example openvalue, openbasevalue and
            // openrepvalue: read both.
            openValue: TransactionXml::optionalAmount($line, 'valueopen')
                ?? TransactionXml::optionalAmount($line, 'openvalue'),
            openBaseValue: TransactionXml::optionalAmount($line, 'basevalueopen')
                ?? TransactionXml::optionalAmount($line, 'openbasevalue'),
            openRepValue: TransactionXml::optionalAmount($line, 'repvalueopen')
                ?? TransactionXml::optionalAmount($line, 'openrepvalue'),
            freeChar: TransactionXml::optionalString($line, 'freechar'),
            comment: TransactionXml::optionalString($line, 'comment'),
            matches: MatchSet::listFromLine($line),
        );
    }

    /**
     * Total as signed amount: positive for an invoice (debit), negative for a credit note.
     */
    public function signedValue(): Amount
    {
        return $this->debitCredit === DebitCredit::Debit ? $this->amounts->value : $this->amounts->value->negate();
    }
}
