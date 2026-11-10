<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\PurchaseTransaction;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\PurchaseTransaction\PurchaseTransactionInput;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\DetailLineInput;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Transaction\VatLineInput;
use SolveBeam\Twinfield\Twinfield;

final class PurchaseTransactionInputTest extends TestCase
{
    public function testWritesTheDocumentedRequest(): void
    {
        // The create example of the Twinfield documentation, in the element order of the SDK.
        $input = PurchaseTransactionInput::invoice('INK', '2000', '20210-5481', new DateTimeImmutable('2021-05-02'))
            ->currency('EUR')
            ->period(Period::fromString('2021/05'))
            ->dueDate(new DateTimeImmutable('2021-05-06'))
            ->paymentReference('+++100/0160/01495+++')
            ->payablesAccount('1600')
            ->raiseWarnings(false)
            ->expense('8020', '100.00', vatCode: 'IH', vatValue: '21.00', description: 'Outfit');

        self::assertSame(
            '<transaction destiny="temporary" raisewarning="false">'
            . '<header><office>001</office><code>INK</code><period>2021/05</period><currency>EUR</currency>'
            . '<date>20210502</date><duedate>20210506</duedate><invoicenumber>20210-5481</invoicenumber>'
            . '<paymentreference>+++100/0160/01495+++</paymentreference></header>'
            . '<lines>'
            . '<line type="total" id="1"><dim1>1600</dim1><dim2>2000</dim2><debitcredit>credit</debitcredit>'
            . '<value>121.00</value></line>'
            . '<line type="detail" id="2"><dim1>8020</dim1><debitcredit>debit</debitcredit><value>100.00</value>'
            . '<description>Outfit</description><vatcode>IH</vatcode><vatvalue>21.00</vatvalue></line>'
            . '</lines>'
            . '</transaction>',
            $input->toXml('001')->toXml(),
        );
    }

    public function testInvoiceExpensesAreDebitAndTheTotalCredit(): void
    {
        $input = PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-118', new DateTimeImmutable('2026-11-09'))
            ->expense('7000', '100.00', vatCode: 'IH', vatValue: '21.00')
            ->expense('7010', '50.00', vatCode: 'IL', vatValue: '4.50');

        self::assertFalse($input->isCreditNote());
        self::assertSame('175.50', $input->total()->value);

        $xpath = $this->xpath($input);
        self::assertSame('credit', $xpath->evaluate('string(//line[@type="total"]/debitcredit)'));
        self::assertSame('175.50', $xpath->evaluate('string(//line[@type="total"]/value)'));
        self::assertSame('debit', $xpath->evaluate('string(//line[@id="2"]/debitcredit)'));
        self::assertSame('debit', $xpath->evaluate('string(//line[@id="3"]/debitcredit)'));
    }

    public function testCreditNoteExpensesAreCreditAndTheTotalDebit(): void
    {
        $input = PurchaseTransactionInput::creditNote('INK', '2000', 'C-2026-007', new DateTimeImmutable('2026-11-09'))
            ->expense('7000', '100.00', vatCode: 'IH', vatValue: '21.00');

        self::assertTrue($input->isCreditNote());
        self::assertSame('-121.00', $input->total()->value);

        $xpath = $this->xpath($input);
        self::assertSame('debit', $xpath->evaluate('string(//line[@type="total"]/debitcredit)'));
        self::assertSame('121.00', $xpath->evaluate('string(//line[@type="total"]/value)'));
        self::assertSame('credit', $xpath->evaluate('string(//line[@id="2"]/debitcredit)'));
        self::assertSame('100.00', $xpath->evaluate('string(//line[@id="2"]/value)'));
    }

    public function testTotalSideFollowsTheLines(): void
    {
        // An invoice with a discount line larger than the costs: the supplier owes us money.
        $input = PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-119', new DateTimeImmutable('2026-11-09'))
            ->expense('7000', '10.00')
            ->line(DetailLineInput::credit('7090', '25.00'));

        self::assertSame('-15.00', $input->total()->value);
        self::assertSame('debit', $this->xpath($input)->evaluate('string(//line[@type="total"]/debitcredit)'));
    }

    public function testCalculatesTheVatWithAVatCode(): void
    {
        $psr17 = new Psr17Factory();
        $vatCode = Twinfield::create(
            TestTokens::provider('https://example.test'),
            ReplayClient::fixture('vat-read'),
            $psr17,
            $psr17,
        )->company('1000')->vatCodes()->get('VH');

        $input = PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-118', new DateTimeImmutable('2026-11-09'))
            ->expense('7000', '99.99', vatCode: $vatCode);

        $xpath = $this->xpath($input);
        self::assertSame('debit', $xpath->evaluate('string(//line[@id="2"]/debitcredit)'));
        self::assertSame('21.00', $xpath->evaluate('string(//line[@id="2"]/vatvalue)'));
        self::assertSame('120.99', $xpath->evaluate('string(//line[@type="total"]/value)'));
    }

    public function testVatLinesReplaceTheVatOfDetailLinesInTheTotal(): void
    {
        $input = PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-118', new DateTimeImmutable('2026-11-09'))
            ->expense('7000', '100.00', vatCode: 'IH', vatValue: '21.00')
            ->vatLine(VatLineInput::debit('IH', '21.01')->turnover('100.00'));

        self::assertSame('121.01', $input->total()->value);
    }

    public function testWritesOptions(): void
    {
        $input = PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-118', new DateTimeImmutable('2026-11-09'))
            ->number(202600021)
            ->ignoreDuplicateInvoiceWarning()
            ->description('Invoice F-2026-118')
            ->comment('Order 778')
            ->excludeFromPaymentRuns()
            ->expense('7000', '100.00', costCenter: '00100', project: 'P0001');

        self::assertSame(
            '<transaction destiny="temporary">'
            . '<header><office>1000</office><code>INK</code><number>202600021</number>'
            . '<date>20261109</date>'
            . '<invoicenumber raisewarning="false">F-2026-118</invoicenumber></header>'
            . '<lines>'
            . '<line type="total" id="1"><dim2>2000</dim2><debitcredit>credit</debitcredit><value>100.00</value>'
            . '<description>Invoice F-2026-118</description><freechar>N</freechar><comment>Order 778</comment></line>'
            . '<line type="detail" id="2"><dim1>7000</dim1><dim2>00100</dim2><dim3>P0001</dim3>'
            . '<debitcredit>debit</debitcredit><value>100.00</value></line>'
            . '</lines>'
            . '</transaction>',
            $input->toXml('1000')->toXml(),
        );
    }

    public function testIncludeInPaymentRunsAgain(): void
    {
        $input = PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-118', new DateTimeImmutable('2026-11-09'))
            ->excludeFromPaymentRuns()
            ->excludeFromPaymentRuns(false)
            ->expense('7000', '1.00');

        self::assertSame(0.0, $this->xpath($input)->evaluate('count(//freechar)'));
    }

    public function testInputsAreImmutable(): void
    {
        $base = PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-118', new DateTimeImmutable('2026-11-09'));
        $withLine = $base->expense('7000', '1.00');

        self::assertSame([], $base->lines());
        self::assertCount(1, $withLine->lines());
        self::assertSame('2000', $withLine->supplier());
        self::assertSame('F-2026-118', $withLine->invoiceNumber());
    }

    public function testNeedsLines(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction in day book INK has no lines: add one with expense() or line().');

        PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-118', new DateTimeImmutable('2026-11-09'))
            ->toXml('1000');
    }

    public function testNeedsAnInvoiceNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A purchase transaction needs an invoice number.');

        PurchaseTransactionInput::invoice('INK', '2000', '', new DateTimeImmutable('2026-11-09'));
    }

    public function testRefusesAnEmptySupplier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('supplier (dim2 of the total line) must not be empty');

        PurchaseTransactionInput::invoice('INK', '', 'F-2026-118', new DateTimeImmutable('2026-11-09'));
    }

    public function testRefusesAnEmptyPayablesAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line dim1 (total line) must not be empty');

        PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-118', new DateTimeImmutable('2026-11-09'))
            ->payablesAccount('');
    }

    public function testRefusesNegativeExpenses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('use the other side');

        PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-118', new DateTimeImmutable('2026-11-09'))
            ->expense('7000', '-1.00');
    }

    private function xpath(PurchaseTransactionInput $input): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML($input->toXml('1000')->toXml());

        return new DOMXPath($document);
    }
}
