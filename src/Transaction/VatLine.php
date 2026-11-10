<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A VAT line (`type="vat"`): the VAT amount of one VAT code.
 *
 * Optional values are null when Twinfield leaves them out of the response.
 */
final readonly class VatLine
{
    /**
     * @param numeric-string $rate
     * @param ?numeric-string $repRate
     */
    public function __construct(
        public int $id,
        /** `dim1`: the VAT balance account. */
        public DimensionReference $account,
        public DebitCredit $debitCredit,
        /** The VAT amount. In the currency of the transaction; always positive, `debitCredit` gives the side. */
        public Amount $value,
        /** In the base currency of the company. */
        public Amount $baseValue,
        /** Exchange rate from the transaction currency to the base currency, e.g. `1` or `1.084500000`. */
        public string $rate,
        /** In the reporting currency; null when the company has none (Twinfield then leaves it out). */
        public ?Amount $repValue,
        /** Exchange rate from the transaction currency to the reporting currency. */
        public ?string $repRate,
        public string $vatCode,
        /** Amount the VAT was calculated on. */
        public ?Amount $vatTurnover,
        public ?Amount $vatBaseTurnover,
        public ?Amount $vatRepTurnover,
        /** ID of the line the VAT belongs to. */
        public ?int $baseline,
        public ?string $description,
        public ?Performance $performance,
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
            vatCode: $line->string('vatcode'),
            vatTurnover: TransactionXml::optionalAmount($line, 'vatturnover'),
            vatBaseTurnover: TransactionXml::optionalAmount($line, 'vatbaseturnover'),
            vatRepTurnover: TransactionXml::optionalAmount($line, 'vatrepturnover'),
            baseline: TransactionXml::optionalInt($line, 'baseline'),
            description: TransactionXml::optionalString($line, 'description'),
            performance: TransactionXml::performance($line),
            matchStatus: $line->enum('matchstatus', MatchStatus::class),
            freeChar: TransactionXml::optionalString($line, 'freechar'),
            comment: TransactionXml::optionalString($line, 'comment'),
        );
    }

    /**
     * Amount as signed value: positive when credit, negative when debit.
     */
    public function signedValue(): Amount
    {
        return $this->debitCredit === DebitCredit::Credit ? $this->value : $this->value->negate();
    }

    public function edit(): VatLineInput
    {
        return VatLineInput::fromLine($this);
    }
}
