<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\SalesTransaction;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\SalesTransaction\SalesTransactionInput;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\DetailLineInput;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;
use SolveBeam\Twinfield\Transaction\TransactionStatus;
use SolveBeam\Twinfield\Transaction\VatLineInput;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\VatCode\VatCode;

final class SalesTransactionInputTest extends TestCase
{
    public function testWritesTheDocumentedRequest(): void
    {
        // The create example of the Twinfield documentation, in the documented element order.
        $input = SalesTransactionInput::invoice('VRK', '1000', '20210-6000', new DateTimeImmutable('2021-05-02'))
            ->currency('EUR')
            ->period(FinancialPeriod::fromString('2021/05'))
            ->dueDate(new DateTimeImmutable('2021-05-06'))
            ->paymentReference('+++100/0160/01495+++')
            ->receivablesAccount('1300')
            ->raiseWarnings(false)
            ->revenue('8020', '100.00', vatCode: 'VH', vatValue: '21.00', description: 'Outfit');

        self::assertSame(
            '<transaction destiny="temporary" raisewarning="false">'
            . '<header><office>001</office><code>VRK</code><period>2021/05</period><currency>EUR</currency>'
            . '<date>20210502</date><duedate>20210506</duedate><invoicenumber>20210-6000</invoicenumber>'
            . '<paymentreference>+++100/0160/01495+++</paymentreference></header>'
            . '<lines>'
            . '<line type="total" id="1"><dim1>1300</dim1><dim2>1000</dim2><debitcredit>debit</debitcredit>'
            . '<value>121.00</value></line>'
            . '<line type="detail" id="2"><dim1>8020</dim1><debitcredit>credit</debitcredit><value>100.00</value>'
            . '<description>Outfit</description><vatcode>VH</vatcode><vatvalue>21.00</vatvalue></line>'
            . '</lines>'
            . '</transaction>',
            $input->toXml('001')->toXml(),
        );
    }

    public function testInvoiceRevenueIsCreditAndTheTotalDebit(): void
    {
        $input = SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->revenue('8000', '100.00', vatCode: 'VH', vatValue: '21.00')
            ->revenue('8010', '50.00', vatCode: 'VL', vatValue: '4.50');

        self::assertFalse($input->isCreditNote());
        self::assertSame('175.50', $input->total()->value);

        $xpath = $this->xpath($input);
        self::assertSame('debit', $xpath->evaluate('string(//line[@type="total"]/debitcredit)'));
        self::assertSame('175.50', $xpath->evaluate('string(//line[@type="total"]/value)'));
        self::assertSame('credit', $xpath->evaluate('string(//line[@id="2"]/debitcredit)'));
        self::assertSame('credit', $xpath->evaluate('string(//line[@id="3"]/debitcredit)'));
    }

    public function testCreditNoteRevenueIsDebitAndTheTotalCredit(): void
    {
        $input = SalesTransactionInput::creditNote('VRK', '1000', '2026-0043', new DateTimeImmutable('2026-11-09'))
            ->revenue('8000', '100.00', vatCode: 'VH', vatValue: '21.00');

        self::assertTrue($input->isCreditNote());
        self::assertSame('-121.00', $input->total()->value);

        $xpath = $this->xpath($input);
        self::assertSame('credit', $xpath->evaluate('string(//line[@type="total"]/debitcredit)'));
        self::assertSame('121.00', $xpath->evaluate('string(//line[@type="total"]/value)'));
        self::assertSame('debit', $xpath->evaluate('string(//line[@id="2"]/debitcredit)'));
        self::assertSame('100.00', $xpath->evaluate('string(//line[@id="2"]/value)'));
    }

    public function testTotalSideFollowsTheLines(): void
    {
        // An invoice with a discount line larger than the revenue: the customer gets money back.
        $input = SalesTransactionInput::invoice('VRK', '1000', '2026-0044', new DateTimeImmutable('2026-11-09'))
            ->revenue('8000', '10.00')
            ->line(DetailLineInput::debit('8090', '25.00'));

        self::assertSame('-15.00', $input->total()->value);
        self::assertSame('credit', $this->xpath($input)->evaluate('string(//line[@type="total"]/debitcredit)'));
    }

    public function testCalculatesTheVatWithAVatCode(): void
    {
        $vh = $this->vatCode();
        $input = SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->revenue('8000', '99.99', vatCode: $vh);

        $xpath = $this->xpath($input);
        self::assertSame('VH', $xpath->evaluate('string(//line[@id="2"]/vatcode)'));
        self::assertSame('21.00', $xpath->evaluate('string(//line[@id="2"]/vatvalue)'));
        self::assertSame('120.99', $xpath->evaluate('string(//line[@type="total"]/value)'));
    }

    public function testCalculatesTheVatWithThePercentageOnTheTransactionDate(): void
    {
        // VH was 19% before 2012-10-01 in the fixture.
        $input = SalesTransactionInput::invoice('VRK', '1000', '2012-0001', new DateTimeImmutable('2012-09-30'))
            ->revenue('8000', '100.00', vatCode: $this->vatCode());

        self::assertSame('119.00', $input->total()->value);
    }

    public function testAGivenVatAmountWinsOverTheVatCode(): void
    {
        $input = SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->revenue('8000', '100.00', vatCode: $this->vatCode(), vatValue: '20.99');

        self::assertSame('120.99', $input->total()->value);
    }

    public function testVatLinesReplaceTheVatOfDetailLinesInTheTotal(): void
    {
        $input = SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->revenue('8000', '100.00', vatCode: 'VH', vatValue: '21.00')
            ->vatLine(VatLineInput::credit('VH', '21.01')->turnover('100.00'));

        self::assertSame('121.01', $input->total()->value);
        self::assertSame('vat', $this->xpath($input)->evaluate('string(//line[@id="3"]/@type)'));
    }

    public function testWritesCostCenterAndProject(): void
    {
        $input = SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->revenue('8000', '50.00', costCenter: '00100', project: 'P0001');

        $xpath = $this->xpath($input);
        self::assertSame('00100', $xpath->evaluate('string(//line[@id="2"]/dim2)'));
        self::assertSame('P0001', $xpath->evaluate('string(//line[@id="2"]/dim3)'));
    }

    public function testWritesOptions(): void
    {
        $input = SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->number(202600012)
            ->ignoreDuplicateInvoiceWarning()
            ->ignoreDateWarning()
            ->autoBalanceVat()
            ->final()
            ->description('Invoice 2026-0042')
            ->comment('Order 778')
            ->excludeFromDirectDebit()
            ->freeText(1, 'Order 778')
            ->revenue('8000', '100.00');

        self::assertSame(TransactionStatus::Final, $input->destiny());
        self::assertSame(
            '<transaction destiny="final" autobalancevat="true">'
            . '<header><office>1000</office><code>VRK</code><number>202600012</number>'
            . '<date raisewarning="false">20261109</date>'
            . '<invoicenumber raisewarning="false">2026-0042</invoicenumber>'
            . '<freetext1>Order 778</freetext1></header>'
            . '<lines>'
            . '<line type="total" id="1"><dim2>1000</dim2><debitcredit>debit</debitcredit><value>100.00</value>'
            . '<description>Invoice 2026-0042</description><freechar>N</freechar><comment>Order 778</comment></line>'
            . '<line type="detail" id="2"><dim1>8000</dim1><debitcredit>credit</debitcredit><value>100.00</value></line>'
            . '</lines>'
            . '</transaction>',
            $input->toXml('1000')->toXml(),
        );
    }

    public function testIncludeInDirectDebitAgain(): void
    {
        $input = SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->excludeFromDirectDebit()
            ->excludeFromDirectDebit(false)
            ->revenue('8000', '1.00');

        self::assertSame(0.0, $this->xpath($input)->evaluate('count(//freechar)'));
    }

    public function testLeavesOutAnEmptyPaymentReference(): void
    {
        $input = SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->paymentReference('')
            ->revenue('8000', '1.00');

        self::assertSame(0.0, $this->xpath($input)->evaluate('count(//paymentreference)'));
    }

    public function testInputsAreImmutable(): void
    {
        $base = SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'));
        $withLine = $base->revenue('8000', '1.00');

        self::assertSame([], $base->lines());
        self::assertCount(1, $withLine->lines());
        self::assertSame('1000', $withLine->customer());
        self::assertSame('2026-0042', $withLine->invoiceNumber());
    }

    public function testNeedsLines(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction in day book VRK has no lines: add one with revenue() or line().');

        SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))->toXml('1000');
    }

    public function testNeedsTheVatAmountWithAVatCodeString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line 1 has VAT code VH without VAT amount');

        SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->revenue('8000', '100.00', vatCode: 'VH')
            ->toXml('1000');
    }

    public function testNeedsAnInvoiceNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A sales transaction needs an invoice number.');

        SalesTransactionInput::invoice('VRK', '1000', '', new DateTimeImmutable('2026-11-09'));
    }

    public function testRefusesATooLongInvoiceNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesTransactionInput::invoice('VRK', '1000', str_repeat('1', 41), new DateTimeImmutable('2026-11-09'));
    }

    public function testRefusesAnEmptyCustomer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('customer (dim2 of the total line) must not be empty');

        SalesTransactionInput::invoice('VRK', '', '2026-0042', new DateTimeImmutable('2026-11-09'));
    }

    public function testRefusesATooLongPaymentReference(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->paymentReference(str_repeat('1', 51));
    }

    public function testRefusesAFreeCharOfMoreThanOneCharacter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->freeChar('NO');
    }

    public function testRefusesNegativeRevenue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('use the other side');

        SalesTransactionInput::invoice('VRK', '1000', '2026-0042', new DateTimeImmutable('2026-11-09'))
            ->revenue('8000', '-1.00');
    }

    private function xpath(SalesTransactionInput $input): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML($input->toXml('1000')->toXml());

        return new DOMXPath($document);
    }

    private function vatCode(): VatCode
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(
            TestTokens::provider('https://example.test'),
            ReplayClient::fixture('vat-read'),
            $psr17,
            $psr17,
        )->company('1000')->vatCodes()->get('VH');
    }
}
