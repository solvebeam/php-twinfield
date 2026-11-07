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
     * @param list<MatchSet> $matches
     */
    public function __construct(
        public int $id,
        /** `dim1`: balance sheet account (e.g. accounts receivable `1300`) or profit and loss account. */
        public DimensionReference $account,
        /** `dim2`: customer, supplier or cost centre. */
        public ?DimensionReference $dim2,
        /** `dim3`: project or asset. */
        public ?DimensionReference $dim3,
        /** Bank and cash: credit when money is received, debit when it is paid. */
        public DebitCredit $debitCredit,
        /** Amount without VAT. */
        public LineAmounts $amounts,
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

    public static function fromXml(XmlElement $line): self
    {
        return new self(
            id: (int) $line->attribute('id'),
            account: TransactionXml::dimension($line, 'dim1'),
            dim2: TransactionXml::optionalDimension($line, 'dim2'),
            dim3: TransactionXml::optionalDimension($line, 'dim3'),
            debitCredit: $line->enum('debitcredit', DebitCredit::class),
            amounts: LineAmounts::fromXml($line),
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
        return $this->debitCredit === DebitCredit::Credit ? $this->amounts->value : $this->amounts->value->negate();
    }

    public function edit(): DetailLineInput
    {
        return DetailLineInput::fromLine($this);
    }
}
