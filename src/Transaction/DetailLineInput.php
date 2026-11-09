<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A detail line to create or update. Immutable: every method returns a new line.
 *
 *   DetailLineInput::credit('1300', '121.00')
 *       ->customer('1000')
 *       ->invoice('2026-0042')
 *       ->description('Payment invoice 2026-0042');
 *
 * Amounts are positive; debit() or credit() gives the side. Bank and cash inputs have
 * receive() and pay(), which choose the side for you.
 */
final class DetailLineInput
{
    private ?string $dim2 = null;

    private ?string $dim3 = null;

    private ?string $vatCode = null;

    private ?Amount $vatValue = null;

    /** @var ?numeric-string */
    private ?string $rate = null;

    private ?Amount $baseValue = null;

    /** @var ?numeric-string */
    private ?string $repRate = null;

    private ?string $invoiceNumber = null;

    private ?string $description = null;

    private ?string $comment = null;

    private ?DateTimeImmutable $dueDate = null;

    private ?DateTimeImmutable $lineDate = null;

    private ?string $destinationCompany = null;

    private ?Performance $performance = null;

    private ?string $freeChar = null;

    /** @var list<MatchSetInput> */
    private array $matches = [];

    private function __construct(
        private readonly DebitCredit $debitCredit,
        private readonly string $account,
        private readonly Amount $value,
    ) {
        TransactionXmlWriter::dimension('account (dim1)', $account);

        if ($value->isNegative()) {
            throw new InvalidArgumentException(sprintf(
                'Line amount must not be negative, %s given: use the other side (debit or credit) instead.',
                $value,
            ));
        }
    }

    /**
     * @param string $account `dim1`: balance sheet or profit and loss account, e.g. `1300` or `4500`.
     * @param Amount|numeric-string $value Amount without VAT, e.g. `'121.00'`.
     */
    public static function debit(string $account, Amount|string $value): self
    {
        return new self(DebitCredit::Debit, $account, Amount::of($value));
    }

    /**
     * @param string $account `dim1`: balance sheet or profit and loss account, e.g. `1300` or `8000`.
     * @param Amount|numeric-string $value Amount without VAT, e.g. `'121.00'`.
     */
    public static function credit(string $account, Amount|string $value): self
    {
        return new self(DebitCredit::Credit, $account, Amount::of($value));
    }

    /**
     * @param Amount|numeric-string $value
     */
    public static function create(DebitCredit $debitCredit, string $account, Amount|string $value): self
    {
        return new self($debitCredit, $account, Amount::of($value));
    }

    /**
     * Input with the values of a line from a response. Base and reporting amounts are left out:
     * Twinfield calculates them again from the rates. Match sets are left out too: they are
     * already matched, so sending them again would match twice.
     */
    public static function fromLine(DetailLine $line): self
    {
        $input = new self($line->debitCredit, $line->account->code, $line->amounts->value);

        $input->dim2 = $line->dim2?->code;
        $input->dim3 = $line->dim3?->code;
        $input->vatCode = $line->vatCode;
        $input->vatValue = $line->vatCode === null ? null : $line->vatValue;
        $input->rate = $line->amounts->rate;
        $input->repRate = $line->amounts->repRate;
        $input->invoiceNumber = $line->invoiceNumber;
        $input->description = $line->description;
        $input->comment = $line->comment;
        $input->dueDate = $line->dueDate;
        $input->lineDate = $line->lineDate;
        $input->destinationCompany = $line->destinationCompany;
        $input->performance = $line->performance;
        $input->freeChar = $line->freeChar;

        return $input;
    }

    /**
     * `dim2`: customer, supplier or cost centre. Null removes it.
     */
    public function dim2(?string $code): self
    {
        if ($code !== null) {
            TransactionXmlWriter::dimension('dim2', $code);
        }

        $line = clone $this;
        $line->dim2 = $code;

        return $line;
    }

    /**
     * `dim3`: project or asset. Null removes it.
     */
    public function dim3(?string $code): self
    {
        if ($code !== null) {
            TransactionXmlWriter::dimension('dim3', $code);
        }

        $line = clone $this;
        $line->dim3 = $code;

        return $line;
    }

    /**
     * The customer (`dim2`); use the accounts receivable account as `dim1`, e.g. `1300`.
     */
    public function customer(?string $code): self
    {
        return $this->dim2($code);
    }

    /**
     * The supplier (`dim2`); use the accounts payable account as `dim1`, e.g. `1600`.
     */
    public function supplier(?string $code): self
    {
        return $this->dim2($code);
    }

    /**
     * The cost center (`dim2`) of a profit and loss account; see `$twinfield->costCenters()`.
     */
    public function costCenter(?string $code): self
    {
        return $this->dim2($code);
    }

    /**
     * The project (`dim3`).
     */
    public function project(?string $code): self
    {
        return $this->dim3($code);
    }

    /**
     * The asset (`dim3`).
     */
    public function asset(?string $code): self
    {
        return $this->dim3($code);
    }

    /**
     * Invoice number, at most 40 characters: for a customer or supplier, the invoice this line pays.
     */
    public function invoice(?string $invoiceNumber): self
    {
        $invoiceNumber = $invoiceNumber === '' ? null : $invoiceNumber;

        if ($invoiceNumber !== null) {
            XmlValue::maxLength('invoice number', $invoiceNumber, 40);
        }

        $line = clone $this;
        $line->invoiceNumber = $invoiceNumber;

        return $line;
    }

    /**
     * At most 40 characters.
     */
    public function description(?string $description): self
    {
        $description = $description === '' ? null : $description;

        if ($description !== null) {
            XmlValue::maxLength('line description', $description, 40);
        }

        $line = clone $this;
        $line->description = $description;

        return $line;
    }

    /**
     * At most 255 characters.
     */
    public function comment(?string $comment): self
    {
        $comment = $comment === '' ? null : $comment;

        if ($comment !== null) {
            XmlValue::maxLength('line comment', $comment, 255);
        }

        $line = clone $this;
        $line->comment = $comment;

        return $line;
    }

    /**
     * VAT code, and optionally the VAT amount. Without amount Twinfield calculates the VAT;
     * with an amount Twinfield checks it and refuses the transaction when it differs.
     *
     *   ->vat('IH', '2.10')
     *
     * @param Amount|numeric-string|null $value
     */
    public function vat(?string $code, Amount|string|null $value = null): self
    {
        $code = $code === '' ? null : $code;

        if ($code === null && $value !== null) {
            throw new InvalidArgumentException('A VAT amount needs a VAT code.');
        }

        if ($code !== null) {
            XmlValue::vatCode($code);
        }

        $value = Amount::ofNullable($value);

        if ($value !== null && $value->isNegative()) {
            throw new InvalidArgumentException(sprintf('VAT amount must not be negative, %s given.', $value));
        }

        $line = clone $this;
        $line->vatCode = $code;
        $line->vatValue = $value;

        return $line;
    }

    /**
     * Exchange rate from the transaction currency to the base currency, e.g. `'0.9215'`.
     * Without it, Twinfield uses its own rate of the transaction date.
     *
     * @param ?numeric-string $rate
     */
    public function rate(?string $rate): self
    {
        $line = clone $this;
        $line->rate = $rate === null ? null : TransactionXmlWriter::rate($rate);

        return $line;
    }

    /**
     * Amount in the base currency, when you know it exactly (e.g. from the bank statement).
     *
     * @param Amount|numeric-string|null $baseValue
     */
    public function baseValue(Amount|string|null $baseValue): self
    {
        $line = clone $this;
        $line->baseValue = Amount::ofNullable($baseValue)?->absolute();

        return $line;
    }

    /**
     * Exchange rate from the transaction currency to the reporting currency.
     *
     * @param ?numeric-string $rate
     */
    public function reportingRate(?string $rate): self
    {
        $line = clone $this;
        $line->repRate = $rate === null ? null : TransactionXmlWriter::rate($rate);

        return $line;
    }

    public function dueDate(?DateTimeInterface $date): self
    {
        $line = clone $this;
        $line->dueDate = $date === null ? null : DateTimeImmutable::createFromInterface($date);

        return $line;
    }

    /**
     * Line date (`currencydate`); only when the day book allows or requires line dates.
     */
    public function lineDate(?DateTimeInterface $date): self
    {
        $line = clone $this;
        $line->lineDate = $date === null ? null : DateTimeImmutable::createFromInterface($date);

        return $line;
    }

    /**
     * Company to post this line in, for intercompany transactions.
     */
    public function destinationCompany(?string $company): self
    {
        $line = clone $this;
        $line->destinationCompany = $company === null || $company === '' ? null : $company;

        return $line;
    }

    /**
     * Performance details; mandatory for an ICT VAT code.
     */
    public function performance(?Performance $performance): self
    {
        $line = clone $this;
        $line->performance = $performance;

        return $line;
    }

    /**
     * Free character field: one character.
     */
    public function freeChar(?string $freeChar): self
    {
        $freeChar = $freeChar === '' ? null : $freeChar;

        if ($freeChar !== null && mb_strlen($freeChar) !== 1) {
            throw new InvalidArgumentException(sprintf('Free character must be one character, "%s" given.', $freeChar));
        }

        $line = clone $this;
        $line->freeChar = $freeChar;

        return $line;
    }

    /**
     * Matches this line with lines of other transactions, e.g. the invoice it pays:
     *
     *   ->match(MatchSetInput::create(new DateTimeImmutable('2026-11-03'))->line('VRK', 202600012, 1, '435.55'))
     *
     * Without a match set, Twinfield can still match the line by its invoice number.
     */
    public function match(MatchSetInput $set): self
    {
        $line = clone $this;
        $line->matches[] = $set;

        return $line;
    }

    /**
     * Replaces all match sets; without arguments it removes them.
     */
    public function withMatches(MatchSetInput ...$sets): self
    {
        $line = clone $this;
        $line->matches = array_values($sets);

        return $line;
    }

    /**
     * @return list<MatchSetInput>
     */
    public function matches(): array
    {
        return $this->matches;
    }

    public function debitCredit(): DebitCredit
    {
        return $this->debitCredit;
    }

    public function account(): string
    {
        return $this->account;
    }

    /**
     * Amount without VAT.
     */
    public function value(): Amount
    {
        return $this->value;
    }

    public function vatValue(): ?Amount
    {
        return $this->vatValue;
    }

    public function vatCode(): ?string
    {
        return $this->vatCode;
    }

    /**
     * Amount without VAT, signed: positive for credit, negative for debit.
     */
    public function signedValue(): Amount
    {
        return $this->debitCredit === DebitCredit::Credit ? $this->value : $this->value->negate();
    }

    /**
     * Amount including the VAT amount you gave, signed: positive for credit, negative for debit.
     */
    public function signedValueIncludingVat(): Amount
    {
        $value = $this->value->plus($this->vatValue ?? Amount::zero());

        return $this->debitCredit === DebitCredit::Credit ? $value : $value->negate();
    }

    public function appendTo(XmlBuilder $xml, DOMElement $lines, int $id): void
    {
        $line = $xml->add($lines, 'line', null, ['type' => LineType::Detail->value, 'id' => (string) $id]);

        $xml->add($line, 'dim1', $this->account);
        TransactionXmlWriter::add($xml, $line, 'dim2', $this->dim2);
        TransactionXmlWriter::add($xml, $line, 'dim3', $this->dim3);
        $xml->add($line, 'debitcredit', $this->debitCredit->value);
        $xml->add($line, 'value', $this->value->value);
        TransactionXmlWriter::add($xml, $line, 'rate', $this->rate);
        TransactionXmlWriter::add($xml, $line, 'basevalue', $this->baseValue?->value);
        TransactionXmlWriter::add($xml, $line, 'reprate', $this->repRate);
        TransactionXmlWriter::add($xml, $line, 'duedate', TransactionXmlWriter::date($this->dueDate));
        TransactionXmlWriter::add($xml, $line, 'currencydate', TransactionXmlWriter::date($this->lineDate));
        TransactionXmlWriter::add($xml, $line, 'invoicenumber', $this->invoiceNumber);
        TransactionXmlWriter::add($xml, $line, 'description', $this->description);
        TransactionXmlWriter::add($xml, $line, 'vatcode', $this->vatCode);
        TransactionXmlWriter::add($xml, $line, 'vatvalue', $this->vatValue?->value);
        TransactionXmlWriter::add($xml, $line, 'destoffice', $this->destinationCompany);
        TransactionXmlWriter::performance($xml, $line, $this->performance);
        TransactionXmlWriter::add($xml, $line, 'freechar', $this->freeChar);
        TransactionXmlWriter::add($xml, $line, 'comment', $this->comment);

        if ($this->matches !== []) {
            $matches = $xml->add($line, 'matches');

            foreach ($this->matches as $set) {
                $set->appendTo($xml, $matches);
            }
        }
    }
}
