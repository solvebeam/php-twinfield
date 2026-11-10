<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;

/**
 * A bank or cash transaction to create or update. Immutable: every method returns a new input.
 *
 *   $input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
 *       ->statement(245, startValue: '1000.00')
 *       ->receive('1300', '121.00', customer: '1000', invoice: '2026-0042')
 *       ->pay('4500', '10.00', vatCode: 'IH', vatValue: '2.10', description: 'Bank costs');
 *
 * receive() and pay() choose the side of the line, and the input calculates the total line
 * and the closing balance, so the transaction always balances:
 *
 * - receive(): money comes in, a credit detail line;
 * - pay(): money goes out, a debit detail line;
 * - total line: the sum including VAT, debit when money came in on balance, credit otherwise;
 * - closing balance: opening balance plus that sum.
 *
 * Use line() and vatLine() for full control over a line.
 */
abstract class StatementTransactionInput extends AbstractTransactionInput
{
    private ?int $statementNumber = null;

    private ?Amount $startValue = null;

    private ?string $description = null;

    final protected function __construct(
        string $dayBook,
        private readonly string $dim1,
        DateTimeImmutable $date,
    ) {
        parent::__construct($dayBook, $date);
        TransactionXmlWriter::dimension('account (dim1 of the total line)', $dim1);
    }

    /**
     * @param string $dayBook Day book (transaction type) code, e.g. `BNK`.
     * @param string $dim1 Balance sheet account of the day book (`dim1` of the total line), e.g. `1010`.
     * @param DateTimeInterface $date Transaction date; Twinfield derives the period from it.
     */
    public static function create(string $dayBook, string $dim1, DateTimeInterface $date): static
    {
        return new static($dayBook, $dim1, DateTimeImmutable::createFromInterface($date));
    }

    /**
     * Statement number and opening balance. With an opening balance, the closing balance is
     * calculated; without one, Twinfield sets both to zero.
     *
     * @param ?int $number Number of the bank or cash statement (not the transaction number).
     * @param Amount|numeric-string|null $startValue Opening balance, e.g. `'1000.00'`; may be negative.
     */
    public function statement(?int $number, Amount|string|null $startValue = null): static
    {
        if ($number !== null && $number < 0) {
            throw new InvalidArgumentException('Statement number must be 0 or more.');
        }

        $input = clone $this;
        $input->statementNumber = $number;
        $input->startValue = Amount::ofNullable($startValue);

        return $input;
    }

    /**
     * Description of the total line, at most 40 characters.
     */
    public function description(?string $description): static
    {
        $input = clone $this;
        $input->description = TransactionXmlWriter::optionalText('total line description', $description, 40);

        return $input;
    }

    /**
     * Money received: a credit detail line, e.g. a customer paying an invoice.
     *
     *   ->receive('1300', '121.00', customer: '1000', invoice: '2026-0042')
     *
     * @param string $dim1 Accounts receivable, e.g. `1300`, or a profit and loss account.
     * @param Amount|numeric-string $amount Amount without VAT.
     * @param Amount|numeric-string|null $vatValue VAT amount; required with a VAT code, so the total line is right.
     */
    public function receive(
        string $dim1,
        Amount|string $amount,
        ?string $customer = null,
        ?string $supplier = null,
        ?string $costCenter = null,
        ?string $project = null,
        ?string $invoice = null,
        ?string $description = null,
        ?string $vatCode = null,
        Amount|string|null $vatValue = null,
    ): static {
        return $this->line(self::detail(
            DetailLineInput::credit($dim1, $amount),
            $customer,
            $supplier,
            $costCenter,
            $project,
            $invoice,
            $description,
            $vatCode,
            $vatValue,
        ));
    }

    /**
     * Money paid: a debit detail line, e.g. paying a supplier invoice or a cost.
     *
     *   ->pay('1600', '50.00', supplier: '2000', invoice: 'INV-778')
     *   ->pay('4500', '10.00', vatCode: 'IH', vatValue: '2.10', description: 'Bank costs')
     *
     * @param string $dim1 Accounts payable, e.g. `1600`, or a cost account.
     * @param Amount|numeric-string $amount Amount without VAT.
     * @param Amount|numeric-string|null $vatValue VAT amount; required with a VAT code, so the total line is right.
     */
    public function pay(
        string $dim1,
        Amount|string $amount,
        ?string $customer = null,
        ?string $supplier = null,
        ?string $costCenter = null,
        ?string $project = null,
        ?string $invoice = null,
        ?string $description = null,
        ?string $vatCode = null,
        Amount|string|null $vatValue = null,
    ): static {
        return $this->line(self::detail(
            DetailLineInput::debit($dim1, $amount),
            $customer,
            $supplier,
            $costCenter,
            $project,
            $invoice,
            $description,
            $vatCode,
            $vatValue,
        ));
    }

    public function dim1(): string
    {
        return $this->dim1;
    }

    /**
     * Sum of all lines including VAT, signed: positive when money comes in, negative when it goes out.
     */
    public function balance(): Amount
    {
        return $this->linesTotal();
    }

    /**
     * Opening balance plus balance(); null without opening balance.
     */
    public function closeValue(): ?Amount
    {
        return $this->startValue?->plus($this->balance());
    }

    protected function lineMethods(): string
    {
        return 'receive(), pay() or line()';
    }

    protected function appendHeader(XmlBuilder $xml, DOMElement $header): void
    {
        TransactionXmlWriter::add(
            $xml,
            $header,
            'statementnumber',
            $this->statementNumber === null ? null : (string) $this->statementNumber,
        );
        TransactionXmlWriter::add($xml, $header, 'startvalue', $this->startValue?->value);
        TransactionXmlWriter::add($xml, $header, 'closevalue', $this->closeValue()?->value);
    }

    protected function appendTotalLine(XmlBuilder $xml, DOMElement $lines): void
    {
        $balance = $this->balance();

        $total = $xml->add($lines, 'line', null, ['type' => LineType::Total->value, 'id' => '1']);
        $xml->add($total, 'dim1', $this->dim1);
        $xml->add($total, 'debitcredit', ($balance->isNegative() ? DebitCredit::Credit : DebitCredit::Debit)->value);
        $xml->add($total, 'value', $balance->absolute()->value);
        TransactionXmlWriter::add($xml, $total, 'description', $this->description);
    }

    /**
     * @param Amount|numeric-string|null $vatValue
     */
    private static function detail(
        DetailLineInput $line,
        ?string $customer,
        ?string $supplier,
        ?string $costCenter,
        ?string $project,
        ?string $invoice,
        ?string $description,
        ?string $vatCode,
        Amount|string|null $vatValue,
    ): DetailLineInput {
        $dim2 = array_filter(
            ['customer' => $customer, 'supplier' => $supplier, 'costCenter' => $costCenter],
            static fn (?string $code): bool => $code !== null,
        );

        if (count($dim2) > 1) {
            throw new InvalidArgumentException(sprintf(
                'A line has one dim2: pass only one of customer, supplier and costCenter, not %s.',
                implode(' and ', array_keys($dim2)),
            ));
        }

        return $line
            ->dim2($dim2 === [] ? null : array_values($dim2)[0])
            ->project($project)
            ->invoice($invoice)
            ->description($description)
            ->vat($vatCode, $vatValue);
    }
}
