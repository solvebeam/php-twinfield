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
    public function __construct(
        public int $id,
        /** `dim1`: the bank or cash balance account, e.g. `1010`. */
        public DimensionReference $account,
        /** Bank and cash: debit when money comes in, credit when it goes out. */
        public DebitCredit $debitCredit,
        /** Total including VAT. */
        public LineAmounts $amounts,
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
            amounts: LineAmounts::fromXml($line),
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
        return $this->debitCredit === DebitCredit::Debit ? $this->amounts->value : $this->amounts->value->negate();
    }
}
