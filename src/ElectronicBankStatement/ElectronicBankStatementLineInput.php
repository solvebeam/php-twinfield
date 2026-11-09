<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\TransactionXmlWriter;

/**
 * A line of an electronic bank statement to import: one payment as the bank reports it.
 *
 * Use `ElectronicBankStatementInput::receive()` and `pay()`; construct it yourself only for full control.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/electronic-bank-statements/#transaction
 */
final readonly class ElectronicBankStatementLineInput
{
    public Amount $value;

    /**
     * @param DebitCredit $debitCredit `Credit`: money received by the bank; `Debit`: money paid by the bank.
     * @param Amount|numeric-string $value Amount of the line, more than zero; the side gives the direction.
     * @param ?string $description Description, at most 500 characters, e.g. `Invoice 2026-0042`.
     * @param ?string $type Transaction type code of the bank, at most 4 characters, e.g. `N100`.
     * @param ?string $reference Reference for own use, at most 40 characters.
     * @param ?string $contraIban IBAN of the other party; not together with `$contraAccount`.
     * @param ?string $contraAccount Account number (BBAN) of the other party; not together with `$contraIban`.
     * @param ?string $contraName Name of the holder of `$contraAccount`.
     * @param ?string $endToEndId Payment reference (purpose of reverse payment).
     * @param ?string $returnReason Drop-out code.
     */
    public function __construct(
        public DebitCredit $debitCredit,
        Amount|string $value,
        public ?string $description = null,
        public ?string $type = null,
        public ?string $reference = null,
        public ?string $contraIban = null,
        public ?string $contraAccount = null,
        public ?string $contraName = null,
        public ?string $endToEndId = null,
        public ?string $returnReason = null,
    ) {
        $this->value = Amount::of($value);

        if (!$this->value->isPositive()) {
            throw new InvalidArgumentException(sprintf(
                'Bank statement line value must be more than zero, got %s; use pay() or receive() for the direction.',
                $this->value,
            ));
        }

        TransactionXmlWriter::optionalText('bank statement line description', $description, 500);
        TransactionXmlWriter::optionalText('bank statement line type', $type, 4);
        TransactionXmlWriter::optionalText('bank statement line reference', $reference, 40);

        if (self::filled($contraIban) && self::filled($contraAccount)) {
            throw new InvalidArgumentException('Use either a contra IBAN or a contra account number, not both.');
        }

        if (self::filled($contraName) && !self::filled($contraAccount)) {
            throw new InvalidArgumentException(
                'A contra name needs a contra account number: Twinfield only has the name on <contraaccount>.',
            );
        }
    }

    /**
     * The value with its sign as seen from the bank account: positive when money comes in.
     */
    public function signedValue(): Amount
    {
        return $this->debitCredit === DebitCredit::Credit ? $this->value : $this->value->negate();
    }

    /**
     * @internal
     */
    public function appendTo(XmlBuilder $xml, DOMElement $transactions): void
    {
        $line = $xml->add($transactions, 'transaction');

        if (self::filled($this->contraAccount)) {
            $xml->add(
                $line,
                'contraaccount',
                $this->contraAccount,
                self::filled($this->contraName) ? ['name' => (string) $this->contraName] : [],
            );
        }

        TransactionXmlWriter::add($xml, $line, 'contraiban', $this->contraIban);
        TransactionXmlWriter::add($xml, $line, 'type', $this->type);
        TransactionXmlWriter::add($xml, $line, 'reference', $this->reference);
        $xml->add($line, 'debitcredit', $this->debitCredit->value);
        $xml->add($line, 'value', XmlValue::money($this->value->value));
        TransactionXmlWriter::add($xml, $line, 'description', $this->description);
        TransactionXmlWriter::add($xml, $line, 'endtoendid', $this->endToEndId);
        TransactionXmlWriter::add($xml, $line, 'returnreason', $this->returnReason);
    }

    private static function filled(?string $value): bool
    {
        return $value !== null && $value !== '';
    }
}
