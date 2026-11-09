<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use Closure;
use DateTimeImmutable;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * What every transaction to create or update has: the header values, detail and VAT lines, and
 * how to save it. Immutable: every method returns a new input.
 *
 * Subclasses add their own header values and the total line, which they calculate from the
 * lines so the transaction always balances.
 */
abstract class AbstractTransactionInput implements TransactionInput
{
    private ?int $number = null;

    private ?string $currency = null;

    private ?FinancialPeriod $period = null;

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

    protected function __construct(
        private readonly string $dayBook,
        private readonly DateTimeImmutable $date,
    ) {
        XmlValue::code($dayBook);
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
     * Adds a detail line with full control, e.g. `line(DetailLineInput::credit('8000', '100.00')->vat('VH', '21.00'))`.
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
     * No warning when the date is outside the period, e.g. for a transaction in a closed month.
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
     * Sum of all detail and VAT lines including VAT, signed: credit positive, debit negative.
     * The total line takes the other side, so the transaction balances.
     */
    protected function linesTotal(): Amount
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
     * The methods that add a detail line, for the message when there are none, e.g. `revenue() or line()`.
     */
    abstract protected function lineMethods(): string;

    /**
     * Header elements after the date and before the free text fields.
     */
    abstract protected function appendHeader(XmlBuilder $xml, DOMElement $header): void;

    /**
     * The total line, with ID 1.
     */
    abstract protected function appendTotalLine(XmlBuilder $xml, DOMElement $lines): void;

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
                'Transaction in day book %s has no lines: add one with %s.',
                $this->dayBook,
                $this->lineMethods(),
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

        $this->appendHeader($xml, $header);

        foreach ($this->freeText as $field => $text) {
            TransactionXmlWriter::add($xml, $header, 'freetext' . $field, $text);
        }

        $lines = $xml->add($transaction, 'lines');

        $this->appendTotalLine($xml, $lines);

        $id = 2;

        foreach ($this->lines as $line) {
            $line->appendTo($xml, $lines, $id++);
        }

        foreach ($this->vatLines as $line) {
            $line->appendTo($xml, $lines, $id++);
        }
    }
}
