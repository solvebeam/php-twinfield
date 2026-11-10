<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeImmutable;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A detail line (`type="detail"`): what the money is for, without VAT. For bank and cash
 * transactions e.g. a customer paying an invoice, or a cost.
 *
 * Optional values are null when Twinfield leaves them out of the response.
 */
final readonly class DetailLine
{
    /**
     * @param numeric-string $rate
     * @param ?numeric-string $repRate
     * @param list<MatchSet> $matches
     */
    public function __construct(
        public int $id,
        /** `dim1`: balance sheet account (e.g. accounts receivable `1300`) or profit and loss account. */
        public DimensionReference $dim1,
        /** `dim2`: customer, supplier or cost centre. */
        public ?DimensionReference $dim2,
        /** `dim3`: project or asset. */
        public ?DimensionReference $dim3,
        /** Bank and cash: credit when money is received, debit when it is paid. */
        public DebitCredit $debitCredit,
        /** Amount without VAT. In the currency of the transaction; always positive, `debitCredit` gives the side. */
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
        public ?DateTimeImmutable $dueDate,
        /** `currencydate`: the line date, when the day book allows line dates. */
        public ?DateTimeImmutable $lineDate,
        public ?string $vatCode,
        public ?Amount $vatValue,
        public ?Amount $vatBaseValue,
        public ?Amount $vatRepValue,
        /** Company to post this line in, for intercompany transactions. */
        public ?string $destinationCompany,
        public ?Performance $performance,
        public MatchStatus $matchStatus,
        /** Level of the matchable dimension, e.g. 2 for a customer. */
        public ?int $matchLevel,
        public ?int $relation,
        /** Amount still open in the transaction currency. */
        public ?Amount $openValue,
        public ?Amount $openBaseValue,
        public ?Amount $openRepValue,
        public ?string $freeChar,
        public ?string $comment,
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
            dim2: TransactionXml::optionalDimension($line, 'dim2'),
            dim3: TransactionXml::optionalDimension($line, 'dim3'),
            debitCredit: $line->enum('debitcredit', DebitCredit::class),
            value: TransactionXml::amount($line, 'value'),
            baseValue: TransactionXml::amount($line, 'basevalue'),
            rate: TransactionXml::rate($line, 'rate'),
            repValue: TransactionXml::optionalAmount($line, 'repvalue'),
            repRate: TransactionXml::optionalRate($line, 'reprate'),
            invoiceNumber: TransactionXml::optionalString($line, 'invoicenumber'),
            description: TransactionXml::optionalString($line, 'description'),
            dueDate: TransactionXml::optionalDate($line, 'duedate'),
            lineDate: TransactionXml::optionalDate($line, 'currencydate'),
            vatCode: TransactionXml::optionalString($line, 'vatcode'),
            vatValue: TransactionXml::optionalAmount($line, 'vatvalue'),
            vatBaseValue: TransactionXml::optionalAmount($line, 'vatbasevalue'),
            vatRepValue: TransactionXml::optionalAmount($line, 'vatrepvalue'),
            destinationCompany: TransactionXml::optionalString($line, 'destoffice'),
            performance: TransactionXml::performance($line),
            matchStatus: $line->enum('matchstatus', MatchStatus::class),
            matchLevel: TransactionXml::optionalInt($line, 'matchlevel'),
            relation: TransactionXml::optionalInt($line, 'relation'),
            // Responses use openvalue, openbasevalue and openrepvalue; the reference table
            // names the last two basevalueopen and repvalueopen.
            openValue: TransactionXml::optionalAmount($line, 'openvalue')
                ?? TransactionXml::optionalAmount($line, 'valueopen'),
            openBaseValue: TransactionXml::optionalAmount($line, 'openbasevalue')
                ?? TransactionXml::optionalAmount($line, 'basevalueopen'),
            openRepValue: TransactionXml::optionalAmount($line, 'openrepvalue')
                ?? TransactionXml::optionalAmount($line, 'repvalueopen'),
            freeChar: TransactionXml::optionalString($line, 'freechar'),
            comment: TransactionXml::optionalString($line, 'comment'),
            matches: MatchSet::listFromLine($line),
        );
    }

    /**
     * Amount as signed value: positive when credit (money received), negative when debit.
     * Without VAT.
     */
    public function signedValue(): Amount
    {
        return $this->debitCredit === DebitCredit::Credit ? $this->value : $this->value->negate();
    }

    public function edit(): DetailLineInput
    {
        return DetailLineInput::fromLine($this);
    }
}
