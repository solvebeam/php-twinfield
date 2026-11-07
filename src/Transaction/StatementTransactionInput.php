<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

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
abstract class StatementTransactionInput implements TransactionInput
{
    private ?int $number = null;

    private ?string $currency = null;

    private ?FinancialPeriod $period = null;

    private ?int $statementNumber = null;

    private ?Amount $startValue = null;

    private ?string $description = null;

    /** @var array{1: ?string, 2: ?string, 3: ?string} */
    private array $freeText = [1 => null, 2 => null, 3 => null];

    /** @var list<DetailLineInput> */
    private array $lines = [];

    /** @var list<VatLineInput> */
    private array $vatLines = [];

    private TransactionStatus $destiny = TransactionStatus::Provisional;

    private ?bool $raiseWarnings = null;

    private ?bool $dateWarning = null;

    private bool $autoBalanceVat = false;

    final protected function __construct(
        private readonly string $dayBook,
        private readonly string $account,
        private readonly DateTimeImmutable $date,
    ) {
        XmlValue::code($dayBook);
        TransactionXmlWriter::dimension('account (dim1 of the total line)', $account);
    }

    /**
     * @param string $dayBook Day book (transaction type) code, e.g. `BNK`.
     * @param string $account Balance sheet account of the day book (`dim1` of the total line), e.g. `1010`.
     * @param DateTimeInterface $date Transaction date; Twinfield derives the period from it.
     */
    public static function create(string $dayBook, string $account, DateTimeInterface $date): static
    {
        return new static($dayBook, $account, DateTimeImmutable::createFromInterface($date));
    }

    /**
     * Transaction number of an existing transaction, to update it. edit() sets it.
     */
    public function number(?int $number): static
    {
        if ($number !== null && $number < 1) {
            throw new InvalidArgumentException('Transaction number must be 1 or more.');
        }

        $input = clone $this;
        $input->number = $number;

        return $input;
    }

    /**
     * Currency code, e.g. `EUR`; without it, the currency of the day book.
     */
    public function currency(?string $currency): static
    {
        if ($currency !== null) {
            XmlValue::code($currency);
        }

        $input = clone $this;
        $input->currency = $currency;

        return $input;
    }

    /**
     * Financial period; without it, Twinfield derives it from the date.
     */
    public function period(?FinancialPeriod $period): static
    {
        $input = clone $this;
        $input->period = $period;

        return $input;
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
     * Free text field 1, 2 or 3, as defined on the day book.
     *
     * @param 1|2|3 $field
     */
    public function freeText(int $field, ?string $text): static
    {
        $input = clone $this;
        $input->freeText[$field] = $text === '' ? null : $text;

        return $input;
    }

    /**
     * Money received: a credit detail line, e.g. a customer paying an invoice.
     *
     *   ->receive('1300', '121.00', customer: '1000', invoice: '2026-0042')
     *
     * @param string $account `dim1`: e.g. accounts receivable `1300`, or a profit and loss account.
     * @param Amount|numeric-string $amount Amount without VAT.
     * @param Amount|numeric-string|null $vatValue VAT amount; required with a VAT code, so the total line is right.
     */
    public function receive(
        string $account,
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
            DetailLineInput::credit($account, $amount),
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
     * @param string $account `dim1`: e.g. accounts payable `1600`, or a cost account.
     * @param Amount|numeric-string $amount Amount without VAT.
     * @param Amount|numeric-string|null $vatValue VAT amount; required with a VAT code, so the total line is right.
     */
    public function pay(
        string $account,
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
            DetailLineInput::debit($account, $amount),
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
     * Adds a detail line with full control, e.g. `line(DetailLineInput::credit('1300', '121.00')->customer('1000'))`.
     */
    public function line(DetailLineInput $line): static
    {
        $input = clone $this;
        $input->lines[] = $line;

        return $input;
    }

    /**
     * Replaces all detail lines.
     */
    public function withLines(DetailLineInput ...$lines): static
    {
        $input = clone $this;
        $input->lines = array_values($lines);

        return $input;
    }

    /**
     * Changes the detail lines one by one, e.g. to change a description after edit():
     *
     *   $input->mapLines(fn (DetailLineInput $line, int $index) => $index === 0 ? $line->description('Paid') : $line);
     *
     * @param Closure(DetailLineInput, int): DetailLineInput $change
     */
    public function mapLines(Closure $change): static
    {
        return $this->withLines(...array_map($change, $this->lines, array_keys($this->lines)));
    }

    /**
     * Adds a VAT line. When there are VAT lines, the total uses their amounts instead of the
     * VAT amounts of the detail lines.
     */
    public function vatLine(VatLineInput $line): static
    {
        $input = clone $this;
        $input->vatLines[] = $line;

        return $input;
    }

    public function withVatLines(VatLineInput ...$lines): static
    {
        $input = clone $this;
        $input->vatLines = array_values($lines);

        return $input;
    }

    /**
     * Save as final: it cannot be changed or deleted afterwards. The default is provisional.
     */
    public function final(): static
    {
        $input = clone $this;
        $input->destiny = TransactionStatus::Final;

        return $input;
    }

    /**
     * Save as provisional (the default): you can still change, post or delete it.
     */
    public function provisional(): static
    {
        $input = clone $this;
        $input->destiny = TransactionStatus::Provisional;

        return $input;
    }

    /**
     * Let Twinfield round VAT differences of at most two cents.
     */
    public function autoBalanceVat(bool $autoBalanceVat = true): static
    {
        $input = clone $this;
        $input->autoBalanceVat = $autoBalanceVat;

        return $input;
    }

    /**
     * Whether Twinfield returns warnings (`raisewarning`, default true).
     */
    public function raiseWarnings(bool $raiseWarnings): static
    {
        $input = clone $this;
        $input->raiseWarnings = $raiseWarnings;

        return $input;
    }

    /**
     * No warning when the date is outside the period, e.g. for a statement in a closed month.
     */
    public function ignoreDateWarning(): static
    {
        $input = clone $this;
        $input->dateWarning = false;

        return $input;
    }

    public function dayBook(): string
    {
        return $this->dayBook;
    }

    public function transactionNumber(): ?int
    {
        return $this->number;
    }

    public function account(): string
    {
        return $this->account;
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * @return list<DetailLineInput>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return list<VatLineInput>
     */
    public function vatLines(): array
    {
        return $this->vatLines;
    }

    public function destiny(): TransactionStatus
    {
        return $this->destiny;
    }

    /**
     * Sum of all lines including VAT, signed: positive when money comes in, negative when it goes out.
     */
    public function balance(): Amount
    {
        $details = array_map(
            fn (DetailLineInput $line): Amount => $this->vatLines === []
                ? $line->signedValueIncludingVat()
                : $line->signedValue(),
            $this->lines,
        );

        $vat = array_map(static fn (VatLineInput $line): Amount => $line->signedValue(), $this->vatLines);

        return Amount::sum([...$details, ...$vat]);
    }

    /**
     * Opening balance plus balance(); null without opening balance.
     */
    public function closeValue(): ?Amount
    {
        return $this->startValue?->plus($this->balance());
    }

    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('transaction', $this->attributes());

        $this->appendContent($xml, $xml->root(), $company);

        return $xml;
    }

    public function appendTo(XmlBuilder $xml, DOMElement $parent, string $company): void
    {
        $transaction = $xml->add($parent, 'transaction', null, $this->attributes());

        $this->appendContent($xml, $transaction, $company);
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        $attributes = ['destiny' => $this->destiny->value];

        if ($this->autoBalanceVat) {
            $attributes['autobalancevat'] = 'true';
        }

        if ($this->raiseWarnings !== null) {
            $attributes['raisewarning'] = XmlValue::boolean($this->raiseWarnings);
        }

        return $attributes;
    }

    private function appendContent(XmlBuilder $xml, DOMElement $transaction, string $company): void
    {
        if ($this->lines === []) {
            throw new InvalidArgumentException(sprintf(
                'Transaction in day book %s has no lines: add one with receive(), pay() or line().',
                $this->dayBook,
            ));
        }

        foreach ($this->lines as $index => $line) {
            if ($this->vatLines === [] && $line->vatCode() !== null && $line->vatValue() === null) {
                throw new InvalidArgumentException(sprintf(
                    'Line %d has VAT code %s without VAT amount: give the amount, e.g. vat(\'%s\', \'2.10\'), '
                        . 'so the total line includes it.',
                    $index + 1,
                    $line->vatCode(),
                    $line->vatCode(),
                ));
            }
        }

        $header = $xml->add($transaction, 'header');

        $xml->add($header, 'office', $company);
        $xml->add($header, 'code', $this->dayBook);
        TransactionXmlWriter::add($xml, $header, 'number', $this->number === null ? null : (string) $this->number);
        TransactionXmlWriter::add($xml, $header, 'period', $this->period === null ? null : (string) $this->period);
        TransactionXmlWriter::add($xml, $header, 'currency', $this->currency);
        $xml->add(
            $header,
            'date',
            XmlValue::date($this->date),
            $this->dateWarning === null ? [] : ['raisewarning' => XmlValue::boolean($this->dateWarning)],
        );
        TransactionXmlWriter::add(
            $xml,
            $header,
            'statementnumber',
            $this->statementNumber === null ? null : (string) $this->statementNumber,
        );
        TransactionXmlWriter::add($xml, $header, 'startvalue', $this->startValue?->value);
        TransactionXmlWriter::add($xml, $header, 'closevalue', $this->closeValue()?->value);

        foreach ($this->freeText as $field => $text) {
            TransactionXmlWriter::add($xml, $header, 'freetext' . $field, $text);
        }

        $lines = $xml->add($transaction, 'lines');
        $balance = $this->balance();

        $total = $xml->add($lines, 'line', null, ['type' => LineType::Total->value, 'id' => '1']);
        $xml->add($total, 'dim1', $this->account);
        $xml->add($total, 'debitcredit', ($balance->isNegative() ? DebitCredit::Credit : DebitCredit::Debit)->value);
        $xml->add($total, 'value', $balance->absolute()->value);
        TransactionXmlWriter::add($xml, $total, 'description', $this->description);

        $id = 2;

        foreach ($this->lines as $line) {
            $line->appendTo($xml, $lines, $id++);
        }

        foreach ($this->vatLines as $line) {
            $line->appendTo($xml, $lines, $id++);
        }
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
