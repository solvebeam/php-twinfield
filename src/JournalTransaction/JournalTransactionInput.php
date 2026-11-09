<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\JournalTransaction;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\Transaction\AbstractTransactionInput;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\DetailLineInput;
use SolveBeam\Twinfield\Transaction\TransactionXmlWriter;
use SolveBeam\Twinfield\VatCode\VatCode;

/**
 * A journal transaction to create or update: a memorial booking, e.g. a correction, a
 * reclassification, the wages or the opening balance. Immutable: every method returns a new input.
 *
 *   $input = JournalTransactionInput::create('MEMO', $date)
 *       ->debit('4008', '435.55')
 *       ->credit('1300', '435.55', customer: '1000', invoice: '11001770', description: 'Invoice paid');
 *
 *   $transaction = $twinfield->journalTransactions()->create($input);
 *
 * A journal transaction has no total line: every line has its own side, and the lines must
 * balance. The input checks that before anything is sent. A line with a VAT code needs the VAT
 * amount: Twinfield books it on the VAT account, on the same side as the line.
 *
 * The kind of journal (memorial, wages, opening balance, year end) is the day book. An opening
 * balance goes in period 0, a year end booking in period 55: `->period(FinancialPeriod::of(2026, 0))`.
 * New transactions are provisional unless you call final().
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/journal-transactions/
 */
final class JournalTransactionInput extends AbstractTransactionInput
{
    private ?Regime $regime = null;

    /**
     * @param string $dayBook Journal day book (transaction type) code, e.g. `MEMO`.
     * @param DateTimeInterface $date Transaction date; Twinfield derives the period from it.
     */
    public static function create(string $dayBook, DateTimeInterface $date): self
    {
        return new self($dayBook, DateTimeImmutable::createFromInterface($date));
    }

    /**
     * A debit detail line, e.g. a cost or an asset.
     *
     *   ->debit('4008', '435.55')
     *   ->debit('4500', '10.00', vatCode: 'IH', vatValue: '2.10', description: 'Office supplies')
     *
     * With a VatCode and no VAT amount, the VAT is calculated with the percentage of the VAT
     * code on the transaction date. With a VAT code as string, give the VAT amount too.
     *
     * @param string $account `dim1`: balance sheet or profit and loss account.
     * @param Amount|numeric-string $amount Amount without VAT.
     * @param ?string $customer `dim2`, with the accounts receivable account as `dim1`.
     * @param ?string $supplier `dim2`, with the accounts payable account as `dim1`.
     * @param ?string $costCenter `dim2` of a profit and loss account.
     * @param ?string $project `dim3`: project or asset.
     * @param Amount|numeric-string|null $vatValue VAT amount; Twinfield refuses the transaction when it differs.
     */
    public function debit(
        string $account,
        Amount|string $amount,
        ?string $customer = null,
        ?string $supplier = null,
        ?string $costCenter = null,
        ?string $project = null,
        ?string $invoice = null,
        ?string $description = null,
        string|VatCode|null $vatCode = null,
        Amount|string|null $vatValue = null,
    ): self {
        return $this->detail(
            DebitCredit::Debit,
            $account,
            $amount,
            $customer,
            $supplier,
            $costCenter,
            $project,
            $invoice,
            $description,
            $vatCode,
            $vatValue,
        );
    }

    /**
     * A credit detail line, e.g. revenue or a customer paying an invoice.
     *
     *   ->credit('1300', '435.55', customer: '1000', invoice: '11001770', description: 'Invoice paid')
     *
     * See debit() for the VAT.
     *
     * @param string $account `dim1`: balance sheet or profit and loss account.
     * @param Amount|numeric-string $amount Amount without VAT.
     * @param ?string $customer `dim2`, with the accounts receivable account as `dim1`.
     * @param ?string $supplier `dim2`, with the accounts payable account as `dim1`.
     * @param ?string $costCenter `dim2` of a profit and loss account.
     * @param ?string $project `dim3`: project or asset.
     * @param Amount|numeric-string|null $vatValue VAT amount; Twinfield refuses the transaction when it differs.
     */
    public function credit(
        string $account,
        Amount|string $amount,
        ?string $customer = null,
        ?string $supplier = null,
        ?string $costCenter = null,
        ?string $project = null,
        ?string $invoice = null,
        ?string $description = null,
        string|VatCode|null $vatCode = null,
        Amount|string|null $vatValue = null,
    ): self {
        return $this->detail(
            DebitCredit::Credit,
            $account,
            $amount,
            $customer,
            $supplier,
            $costCenter,
            $project,
            $invoice,
            $description,
            $vatCode,
            $vatValue,
        );
    }

    /**
     * Regime the transaction is booked in; null leaves it to Twinfield, which uses `Generic`:
     * visible in all regimes.
     */
    public function regime(?Regime $regime): self
    {
        $input = clone $this;
        $input->regime = $regime;

        return $input;
    }

    /**
     * Sum of all lines including VAT, signed: credit positive, debit negative. Zero when the
     * transaction balances.
     */
    public function balance(): Amount
    {
        return $this->linesTotal();
    }

    protected function lineMethods(): string
    {
        return 'debit(), credit() or line()';
    }

    protected function validate(): void
    {
        if (count($this->lines()) + count($this->vatLines()) < 2) {
            throw new InvalidArgumentException(sprintf(
                'Journal transaction in day book %s has one line: it needs at least two that balance.',
                $this->dayBook(),
            ));
        }

        $balance = $this->balance();

        if (!$balance->isZero()) {
            throw new InvalidArgumentException(sprintf(
                'Journal transaction in day book %s does not balance: the credit lines are %s %s than the debit lines.',
                $this->dayBook(),
                $balance->absolute(),
                $balance->isNegative() ? 'less' : 'more',
            ));
        }
    }

    protected function appendHeader(XmlBuilder $xml, DOMElement $header): void
    {
        TransactionXmlWriter::add($xml, $header, 'regime', $this->regime?->value);
    }

    protected function appendTotalLine(XmlBuilder $xml, DOMElement $lines): void
    {
        // A journal transaction has no total line: the lines balance by themselves.
    }

    /**
     * @param Amount|numeric-string $amount
     * @param Amount|numeric-string|null $vatValue
     */
    private function detail(
        DebitCredit $debitCredit,
        string $account,
        Amount|string $amount,
        ?string $customer,
        ?string $supplier,
        ?string $costCenter,
        ?string $project,
        ?string $invoice,
        ?string $description,
        string|VatCode|null $vatCode,
        Amount|string|null $vatValue,
    ): self {
        $amount = Amount::of($amount);

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

        if ($vatCode instanceof VatCode) {
            $vatValue ??= $vatCode->vatOn($amount, $this->date());
            $vatCode = $vatCode->code;
        }

        return $this->line(
            DetailLineInput::create($debitCredit, $account, $amount)
                ->dim2($dim2 === [] ? null : array_values($dim2)[0])
                ->project($project)
                ->invoice($invoice)
                ->description($description)
                ->vat($vatCode, $vatValue),
        );
    }
}
