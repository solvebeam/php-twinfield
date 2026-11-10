<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\TransactionXmlWriter;

/**
 * An electronic bank statement to import. Immutable: every method returns a new input.
 *
 *   $input = ElectronicBankStatementInput::forIban('NL91ABNA0417164300')
 *       ->date(new DateTimeImmutable('2026-11-09'))
 *       ->number(2, startValue: '7.68', closeValue: '151.18')
 *       ->receive('151.00', 'Invoice 2026-0042', contraIban: 'NL20INGB0001234567', type: 'N100')
 *       ->pay('7.50', 'Bank costs', type: 'N999');
 *
 * Twinfield finds the bank book by its account number, IBAN or code: pick one with forAccount(),
 * forIban() or forBankBook(). Everything else is optional; Twinfield fills in the defaults below.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/electronic-bank-statements/#statement
 */
final class ElectronicBankStatementInput
{
    private ?DateTimeImmutable $date = null;

    private ?string $currency = null;

    private ?int $number = null;

    private ?Amount $startValue = null;

    private ?Amount $closeValue = null;

    private ?string $sortCode = null;

    /** @var list<ElectronicBankStatementLineInput> */
    private array $lines = [];

    /**
     * @param 'account'|'iban'|'code' $identifier
     */
    private function __construct(
        private readonly string $identifier,
        private readonly string $identifierValue,
    ) {
    }

    /**
     * For the bank book with this account number (BBAN).
     */
    public static function forAccount(string $account): self
    {
        return new self('account', self::notEmpty('account number', $account));
    }

    /**
     * For the bank book with this IBAN, e.g. `NL91ABNA0417164300`.
     */
    public static function forIban(string $iban): self
    {
        return new self('iban', self::notEmpty('IBAN', $iban));
    }

    /**
     * For the bank book with this code, e.g. `BNK`.
     */
    public static function forBankBook(string $code): self
    {
        return new self('code', XmlValue::code($code));
    }

    /**
     * Statement date. Without one, Twinfield uses the current date.
     */
    public function date(DateTimeInterface $date): self
    {
        $input = clone $this;
        $input->date = DateTimeImmutable::createFromInterface($date);

        return $input;
    }

    /**
     * Currency code, e.g. `EUR`. Without one, Twinfield uses the currency of the bank book.
     */
    public function currency(string $currency): self
    {
        $input = clone $this;
        $input->currency = XmlValue::code($currency);

        return $input;
    }

    /**
     * Statement number, with the opening and closing balance.
     *
     * Without a number, Twinfield takes the last statement number plus one. Without an opening
     * balance, Twinfield takes the closing balance of the previous statement; without a closing
     * balance, it adds the lines to the opening balance. With both, this input checks that the
     * lines add up.
     *
     * @param ?int $number Number of the bank statement (not a transaction number), 1 or more.
     * @param Amount|numeric-string|null $startValue Opening balance, e.g. `'7.68'`; may be negative.
     * @param Amount|numeric-string|null $closeValue Closing balance, e.g. `'151.18'`; may be negative.
     */
    public function number(
        ?int $number,
        Amount|string|null $startValue = null,
        Amount|string|null $closeValue = null,
    ): self {
        if ($number !== null && $number < 1) {
            throw new InvalidArgumentException(sprintf('Bank statement number must be 1 or more, got %d.', $number));
        }

        $input = clone $this;
        $input->number = $number;
        $input->startValue = Amount::ofNullable($startValue);
        $input->closeValue = Amount::ofNullable($closeValue);

        return $input;
    }

    /**
     * Sorting code; required when the bank of the bank book has a clearing code.
     */
    public function sortCode(string $sortCode): self
    {
        $input = clone $this;
        $input->sortCode = self::notEmpty('sort code', $sortCode);

        return $input;
    }

    /**
     * Money received by the bank (`credit`).
     *
     * @param Amount|numeric-string $value More than zero, e.g. `'151.00'`.
     */
    public function receive(
        Amount|string $value,
        ?string $description = null,
        ?string $type = null,
        ?string $reference = null,
        ?string $contraIban = null,
        ?string $contraAccount = null,
        ?string $contraName = null,
        ?string $endToEndId = null,
        ?string $returnReason = null,
    ): self {
        return $this->line(new ElectronicBankStatementLineInput(
            DebitCredit::Credit,
            $value,
            $description,
            $type,
            $reference,
            $contraIban,
            $contraAccount,
            $contraName,
            $endToEndId,
            $returnReason,
        ));
    }

    /**
     * Money paid by the bank (`debit`).
     *
     * @param Amount|numeric-string $value More than zero, e.g. `'7.50'`.
     */
    public function pay(
        Amount|string $value,
        ?string $description = null,
        ?string $type = null,
        ?string $reference = null,
        ?string $contraIban = null,
        ?string $contraAccount = null,
        ?string $contraName = null,
        ?string $endToEndId = null,
        ?string $returnReason = null,
    ): self {
        return $this->line(new ElectronicBankStatementLineInput(
            DebitCredit::Debit,
            $value,
            $description,
            $type,
            $reference,
            $contraIban,
            $contraAccount,
            $contraName,
            $endToEndId,
            $returnReason,
        ));
    }

    public function line(ElectronicBankStatementLineInput $line): self
    {
        $input = clone $this;
        $input->lines[] = $line;

        return $input;
    }

    /**
     * @return list<ElectronicBankStatementLineInput>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * Sum of the lines, positive when more money came in than went out.
     */
    public function total(): Amount
    {
        $total = Amount::zero();

        foreach ($this->lines as $line) {
            $total = $total->plus($line->signedValue());
        }

        return $total;
    }

    /**
     * The request for this statement: `<statements>` with one `<statement>`, e.g. to show or log it.
     *
     * @param bool $importDuplicate Also import a statement Twinfield already has (`importduplicate="1"`).
     *
     * @internal
     */
    public function toXml(string $company, bool $importDuplicate = false): XmlBuilder
    {
        $xml = XmlBuilder::create('statements', ['importduplicate' => $importDuplicate ? '1' : '0']);

        $this->appendTo($xml, $xml->root(), $company);

        return $xml;
    }

    /**
     * Adds `<statement target="electronicstatements">` to `<statements>`.
     *
     * @internal
     */
    public function appendTo(XmlBuilder $xml, DOMElement $statements, string $company): void
    {
        $this->validate();

        $statement = $xml->add($statements, 'statement', null, ['target' => 'electronicstatements']);

        // Element order of the documentation; Twinfield fills in what is left out.
        $elements = [
            'account' => $this->identifier === 'account' ? $this->identifierValue : null,
            'iban' => $this->identifier === 'iban' ? $this->identifierValue : null,
            'date' => TransactionXmlWriter::date($this->date),
            'currency' => $this->currency,
            'statementnumber' => $this->number === null ? null : (string) $this->number,
            'code' => $this->identifier === 'code' ? $this->identifierValue : null,
            'office' => $company,
            'startvalue' => $this->startValue === null ? null : XmlValue::money($this->startValue->value),
            'closevalue' => $this->closeValue === null ? null : XmlValue::money($this->closeValue->value),
            'sortcode' => $this->sortCode,
        ];

        foreach ($elements as $name => $value) {
            TransactionXmlWriter::add($xml, $statement, $name, $value);
        }

        $transactions = $xml->add($statement, 'transactions');

        foreach ($this->lines as $line) {
            $line->appendTo($xml, $transactions);
        }
    }

    private function validate(): void
    {
        if ($this->lines === []) {
            throw new InvalidArgumentException('A bank statement needs at least one line: use receive() or pay().');
        }

        if ($this->startValue === null || $this->closeValue === null) {
            return;
        }

        $expected = $this->startValue->plus($this->total());

        if (!$expected->equals($this->closeValue)) {
            throw new InvalidArgumentException(sprintf(
                'The lines do not add up: opening balance %s plus %s is %s, but the closing balance is %s.',
                $this->startValue,
                $this->total(),
                $expected,
                $this->closeValue,
            ));
        }
    }

    private static function notEmpty(string $field, string $value): string
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Bank statement %s must not be empty.', $field));
        }

        return $value;
    }
}
