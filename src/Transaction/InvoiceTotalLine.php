<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The total line (`type="total"`) of a sales or purchase transaction: what the customer owes or
 * what is owed to the supplier, including VAT.
 *
 * Optional values are null when Twinfield leaves them out of the response.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/
 * @see https://developers.twinfield.com/documentation/api/transaction-data/purchase-transactions/
 */
final readonly class InvoiceTotalLine
{
    /**
     * @param numeric-string $rate
     * @param ?numeric-string $repRate
     * @param list<MatchSet> $matches
     */
    public function __construct(
        public int $id,
        /** `dim1`: the accounts receivable (e.g. `1300`) or payable (e.g. `1600`) balance account. */
        public DimensionReference $dim1,
        /** `dim2`: the customer or supplier. */
        public DimensionReference $dim2,
        /** Sales: debit for an invoice, credit for a credit note. Purchase: the other way round. */
        public DebitCredit $debitCredit,
        /** Total including VAT. In the currency of the transaction; always positive, `debitCredit` gives the side. */
        public Amount $value,
        /** In the base currency of the company. */
        public Amount $baseValue,
        /** Exchange rate from the transaction currency to the base currency, e.g. `1` or `1.084500000`. */
        public string $rate,
        /** In the reporting currency; null when the company has none (Twinfield then leaves it out). */
        public ?Amount $repValue,
        /** Exchange rate from the transaction currency to the reporting currency. */
        public ?string $repRate,
        public ?string $description,
        public ?Amount $vatTotal,
        public ?Amount $vatBaseTotal,
        public ?Amount $vatRepTotal,
        /** Payment status: `available` while (partly) unpaid, `matched` when paid. */
        public MatchStatus $matchStatus,
        /** Level of the matchable dimension, e.g. 2 for the customer or supplier. */
        public ?int $matchLevel,
        public ?int $relation,
        /** Date on which the invoice was matched; null while it is not (fully) matched. */
        public ?DateTimeImmutable $matchDate,
        /** Amount still open in the transaction currency. */
        public ?Amount $openValue,
        /**
         * Amount still open in the base currency, signed: positive for a debit total line (sales
         * invoice), negative for a credit total line (purchase invoice).
         */
        public ?Amount $openBaseValue,
        /** Amount still open in the reporting currency. */
        public ?Amount $openRepValue,
        /** `N`: the invoice is left out of the direct debit runs (sales) or payment runs (purchase) of Twinfield. */
        public ?string $freeChar,
        public ?string $comment,
        /** Payments matched with this invoice; empty when nothing is matched. */
        public array $matches,
    ) {
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $line): self
    {
        return new self(
            id: (int) $line->attribute('id'),
            dim1: TransactionXml::dimension($line, 'dim1'),
            dim2: TransactionXml::dimension($line, 'dim2'),
            debitCredit: $line->enum('debitcredit', DebitCredit::class),
            value: TransactionXml::amount($line, 'value'),
            baseValue: TransactionXml::amount($line, 'basevalue'),
            rate: TransactionXml::rate($line, 'rate'),
            repValue: TransactionXml::optionalAmount($line, 'repvalue'),
            repRate: TransactionXml::optionalRate($line, 'reprate'),
            description: TransactionXml::optionalString($line, 'description'),
            vatTotal: TransactionXml::optionalAmount($line, 'vattotal'),
            vatBaseTotal: TransactionXml::optionalAmount($line, 'vatbasetotal'),
            vatRepTotal: TransactionXml::optionalAmount($line, 'vatreptotal'),
            matchStatus: $line->enum('matchstatus', MatchStatus::class),
            matchLevel: TransactionXml::optionalInt($line, 'matchlevel'),
            relation: TransactionXml::optionalInt($line, 'relation'),
            matchDate: TransactionXml::optionalDate($line, 'matchdate'),
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
}
