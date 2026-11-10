<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A VAT line to create or update. Immutable: every method returns a new line.
 *
 *   VatLineInput::debit('IH', '2.10')->turnover('10.00');
 *
 * VAT lines are optional: a VAT code with amount on a detail line is usually enough. When you
 * add VAT lines, Twinfield checks them against its own calculation and refuses the transaction
 * when they differ (up to two cents with autoBalanceVat()).
 */
final class VatLineInput
{
    private ?string $dim1 = null;

    private ?Amount $turnover = null;

    private ?int $baseline = null;

    private ?string $description = null;

    private ?string $comment = null;

    private ?Performance $performance = null;

    private ?string $freeChar = null;

    private function __construct(
        private readonly DebitCredit $debitCredit,
        private readonly string $vatCode,
        private readonly Amount $value,
    ) {
        if ($vatCode === '') {
            throw new InvalidArgumentException('VAT line needs a VAT code.');
        }

        XmlValue::vatCode($vatCode);

        if ($value->isNegative()) {
            throw new InvalidArgumentException(sprintf(
                'VAT amount must not be negative, %s given: use the other side (debit or credit) instead.',
                $value,
            ));
        }
    }

    /**
     * @param Amount|numeric-string $value The VAT amount.
     */
    public static function debit(string $vatCode, Amount|string $value): self
    {
        return new self(DebitCredit::Debit, $vatCode, Amount::of($value));
    }

    /**
     * @param Amount|numeric-string $value The VAT amount.
     */
    public static function credit(string $vatCode, Amount|string $value): self
    {
        return new self(DebitCredit::Credit, $vatCode, Amount::of($value));
    }

    /**
     * @param Amount|numeric-string $value
     */
    public static function create(DebitCredit $debitCredit, string $vatCode, Amount|string $value): self
    {
        return new self($debitCredit, $vatCode, Amount::of($value));
    }

    public static function fromLine(VatLine $line): self
    {
        $input = new self($line->debitCredit, $line->vatCode, $line->value);

        $input->dim1 = $line->dim1->code;
        $input->turnover = $line->vatTurnover;
        $input->baseline = $line->baseline;
        $input->description = $line->description;
        $input->comment = $line->comment;
        $input->performance = $line->performance;
        $input->freeChar = $line->freeChar;

        return $input;
    }

    /**
     * `dim1`: the VAT balance account. Without it, Twinfield takes the account of the VAT code.
     */
    public function dim1(?string $dim1): self
    {
        if ($dim1 !== null) {
            TransactionXmlWriter::dimension('VAT account (dim1)', $dim1);
        }

        $line = clone $this;
        $line->dim1 = $dim1;

        return $line;
    }

    /**
     * Amount the VAT was calculated on.
     *
     * @param Amount|numeric-string|null $turnover
     */
    public function turnover(Amount|string|null $turnover): self
    {
        $line = clone $this;
        $line->turnover = Amount::ofNullable($turnover);

        return $line;
    }

    /**
     * ID of the line this VAT belongs to. Lines get IDs in the order you add them, starting
     * at 2 when the transaction has a total line (ID 1).
     */
    public function baseline(?int $lineId): self
    {
        if ($lineId !== null && $lineId < 1) {
            throw new InvalidArgumentException('Baseline must be a line ID of 1 or more.');
        }

        $line = clone $this;
        $line->baseline = $lineId;

        return $line;
    }

    public function description(?string $description): self
    {
        $line = clone $this;
        $line->description = TransactionXmlWriter::optionalText('line description', $description, 40);

        return $line;
    }

    public function comment(?string $comment): self
    {
        $line = clone $this;
        $line->comment = TransactionXmlWriter::optionalText('line comment', $comment, 255);

        return $line;
    }

    public function performance(?Performance $performance): self
    {
        $line = clone $this;
        $line->performance = $performance;

        return $line;
    }

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

    public function debitCredit(): DebitCredit
    {
        return $this->debitCredit;
    }

    public function vatCode(): string
    {
        return $this->vatCode;
    }

    public function value(): Amount
    {
        return $this->value;
    }

    /**
     * The VAT amount, signed: positive for credit, negative for debit.
     */
    public function signedValue(): Amount
    {
        return $this->debitCredit === DebitCredit::Credit ? $this->value : $this->value->negate();
    }

    public function appendTo(XmlBuilder $xml, DOMElement $lines, int $id): void
    {
        $line = $xml->add($lines, 'line', null, ['type' => LineType::Vat->value, 'id' => (string) $id]);

        TransactionXmlWriter::add($xml, $line, 'dim1', $this->dim1);
        $xml->add($line, 'debitcredit', $this->debitCredit->value);
        $xml->add($line, 'value', $this->value->value);
        TransactionXmlWriter::add($xml, $line, 'vatturnover', $this->turnover?->value);
        $xml->add($line, 'vatcode', $this->vatCode);
        TransactionXmlWriter::add($xml, $line, 'baseline', $this->baseline === null ? null : (string) $this->baseline);
        TransactionXmlWriter::add($xml, $line, 'description', $this->description);
        TransactionXmlWriter::performance($xml, $line, $this->performance);
        TransactionXmlWriter::add($xml, $line, 'freechar', $this->freeChar);
        TransactionXmlWriter::add($xml, $line, 'comment', $this->comment);
    }
}
