<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The total line (`type="total"`): for bank and cash transactions the bank or cash account,
 * with the total of all other lines including VAT.
 *
 * Optional values are null when Twinfield leaves them out of the response.
 */
final readonly class TotalLine
{
    /**
     * @param numeric-string $rate
     * @param ?numeric-string $repRate
     */
    public function __construct(
        public int $id,
        /** `dim1`: the bank or cash balance account, e.g. `1010`. */
        public DimensionReference $account,
        /** Bank and cash: debit when money comes in, credit when it goes out. */
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
        public ?string $invoiceNumber,
        public ?string $description,
        public ?Amount $vatTotal,
        public ?Amount $vatBaseTotal,
        public ?Amount $vatRepTotal,
        public MatchStatus $matchStatus,
        public ?string $freeChar,
        public ?string $comment,
    ) {
    }

    public static function fromXml(XmlElement $line): self
    {
        return new self(
            id: (int) $line->attribute('id'),
            account: TransactionXml::dimension($line, 'dim1'),
            debitCredit: $line->enum('debitcredit', DebitCredit::class),
            value: TransactionXml::amount($line, 'value'),
            baseValue: TransactionXml::amount($line, 'basevalue'),
            rate: TransactionXml::rate($line, 'rate'),
            repValue: TransactionXml::optionalAmount($line, 'repvalue'),
            repRate: TransactionXml::optionalRate($line, 'reprate'),
            invoiceNumber: TransactionXml::optionalString($line, 'invoicenumber'),
            description: TransactionXml::optionalString($line, 'description'),
            vatTotal: TransactionXml::optionalAmount($line, 'vattotal'),
            vatBaseTotal: TransactionXml::optionalAmount($line, 'vatbasetotal'),
            vatRepTotal: TransactionXml::optionalAmount($line, 'vatreptotal'),
            matchStatus: $line->enum('matchstatus', MatchStatus::class),
            freeChar: TransactionXml::optionalString($line, 'freechar'),
            comment: TransactionXml::optionalString($line, 'comment'),
        );
    }

    /**
     * Total as signed amount: positive when debit (money in), negative when credit (money out).
     */
    public function signedValue(): Amount
    {
        return $this->debitCredit === DebitCredit::Debit ? $this->value : $this->value->negate();
    }
}
