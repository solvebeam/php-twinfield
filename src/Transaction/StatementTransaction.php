<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A bank or cash transaction as Twinfield returns it: one statement, with a total line on the
 * bank or cash account and detail (and VAT) lines for what the money is for.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/bank-transactions/
 * @see https://developers.twinfield.com/documentation/api/transaction-data/cash-transactions/
 */
abstract readonly class StatementTransaction
{
    /**
     * @param list<DetailLine> $details
     * @param list<VatLine> $vat
     */
    final public function __construct(
        public TransactionHeader $header,
        /** Number of the bank or cash statement (not the transaction number); null when not set. */
        public ?int $statementNumber,
        /** Opening balance; zero when not set. */
        public Amount $startValue,
        /** Closing balance; zero when not set. */
        public Amount $closeValue,
        /** The line on the bank or cash account, with the total including VAT. */
        public TotalLine $total,
        public array $details,
        public array $vat,
    ) {
    }

    /**
     * From a `<transaction>` element of a read or save response.
     */
    public static function fromXml(XmlElement $transaction): static
    {
        $header = $transaction->child('header');
        $linesElement = $transaction->child('lines');
        $lines = TransactionLines::fromXml($linesElement);

        return new static(
            header: TransactionHeader::fromXml($transaction),
            statementNumber: TransactionXml::optionalInt($header, 'statementnumber'),
            startValue: TransactionXml::optionalAmount($header, 'startvalue') ?? Amount::zero(),
            closeValue: TransactionXml::optionalAmount($header, 'closevalue') ?? Amount::zero(),
            total: $lines->requireTotal($linesElement),
            details: $lines->details,
            vat: $lines->vat,
        );
    }

    /**
     * Shortcut for `$transaction->header->number`.
     */
    public function number(): int
    {
        return $this->header->number;
    }

    /**
     * Shortcut for `$transaction->header->dayBook`.
     */
    public function dayBook(): string
    {
        return $this->header->dayBook;
    }

    /**
     * Total including VAT, signed: positive when money came in, negative when it went out.
     */
    public function balance(): Amount
    {
        return $this->total->signedValue();
    }

    /**
     * Input with all values of this transaction, to change and pass to update().
     */
    abstract public function edit(): StatementTransactionInput;

    /**
     * @template T of StatementTransactionInput
     *
     * @param T $input
     *
     * @return T
     */
    protected function fill(StatementTransactionInput $input): StatementTransactionInput
    {
        $header = $this->header;

        return $input
            ->number($header->number)
            ->currency($header->currency)
            ->period($header->period)
            ->statement($this->statementNumber, $this->startValue)
            ->description($this->total->description)
            ->freeText(1, $header->freeText1)
            ->freeText(2, $header->freeText2)
            ->freeText(3, $header->freeText3)
            ->withLines(...array_map(static fn (DetailLine $line): DetailLineInput => $line->edit(), $this->details))
            ->withVatLines(...array_map(static fn (VatLine $line): VatLineInput => $line->edit(), $this->vat));
    }
}
