<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\JournalTransaction;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\JournalTransaction\JournalTransactionInput;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\DetailLineInput;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;
use SolveBeam\Twinfield\Transaction\TransactionStatus;
use SolveBeam\Twinfield\Transaction\VatLineInput;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\VatCode\VatCode;

final class JournalTransactionInputTest extends TestCase
{
    public function testWritesTheDocumentedRequest(): void
    {
        // The create example of the Twinfield documentation: no total line, IDs from 1.
        $input = JournalTransactionInput::create('MEMO', new DateTimeImmutable('2021-11-04'))
            ->currency('EUR')
            ->debit('4008', '435.55')
            ->credit('1300', '435.55', customer: '1000', invoice: '11001770', description: 'Invoice paid');

        self::assertSame(
            '<transaction destiny="temporary">'
            . '<header><office>001</office><code>MEMO</code><currency>EUR</currency><date>20211104</date></header>'
            . '<lines>'
            . '<line type="detail" id="1"><dim1>4008</dim1><debitcredit>debit</debitcredit>'
            . '<value>435.55</value></line>'
            . '<line type="detail" id="2"><dim1>1300</dim1><dim2>1000</dim2><debitcredit>credit</debitcredit>'
            . '<value>435.55</value><invoicenumber>11001770</invoicenumber>'
            . '<description>Invoice paid</description></line>'
            . '</lines>'
            . '</transaction>',
            $input->toXml('001')->toXml(),
        );
        self::assertTrue($input->balance()->isZero());
        self::assertSame(TransactionStatus::Provisional, $input->destiny());
    }

    public function testWritesTheHeaderInTheDocumentedOrder(): void
    {
        $input = JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-01-01'))
            ->number(202600003)
            ->period(FinancialPeriod::of(2026, 0))
            ->currency('EUR')
            ->regime(Regime::Fiscal)
            ->ignoreDateWarning()
            ->freeText(1, 'Opening balance')
            ->final()
            ->debit('0100', '1000.00')
            ->credit('0500', '1000.00');

        self::assertStringStartsWith(
            '<transaction destiny="final"><header><office>1000</office><code>MEMO</code>'
            . '<number>202600003</number><period>2026/00</period><currency>EUR</currency>'
            . '<date raisewarning="false">20260101</date><regime>Fiscal</regime>'
            . '<freetext1>Opening balance</freetext1></header>',
            $input->toXml('1000')->toXml(),
        );
    }

    public function testLeavesTheRegimeOutByDefault(): void
    {
        $input = JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-09'))
            ->debit('4008', '1.00')
            ->credit('1300', '1.00');

        self::assertSame(0.0, $this->xpath($input)->evaluate('count(//regime)'));
    }

    public function testVatOfADetailLineCountsOnItsSide(): void
    {
        // Private use of a car: the VAT is booked on the VAT account, credit like the line.
        $input = JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-30'))
            ->credit('8199', '1000.00', vatCode: 'PRIVE-H', vatValue: '210.00', description: 'Private use car')
            ->debit('8199', '1000.00', description: 'Private use car')
            ->debit('4406', '210.00', costCenter: '00100', project: 'P0001');

        self::assertTrue($input->balance()->isZero());

        $xpath = $this->xpath($input);
        self::assertSame('PRIVE-H', $xpath->evaluate('string(//line[@id="1"]/vatcode)'));
        self::assertSame('210.00', $xpath->evaluate('string(//line[@id="1"]/vatvalue)'));
        self::assertSame('00100', $xpath->evaluate('string(//line[@id="3"]/dim2)'));
        self::assertSame('P0001', $xpath->evaluate('string(//line[@id="3"]/dim3)'));
        self::assertSame(0.0, $xpath->evaluate('count(//line[@type="total"])'));
    }

    public function testVatLinesFollowTheDetailLines(): void
    {
        $input = JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-30'))
            ->credit('8199', '1000.00', vatCode: 'PRIVE-H', vatValue: '210.00')
            ->debit('4406', '1210.00')
            ->vatLine(VatLineInput::credit('PRIVE-H', '210.00')->turnover('1000.00')->baseline(1));

        // With VAT lines, their amounts count instead of the VAT of the detail lines.
        self::assertTrue($input->balance()->isZero());

        $xpath = $this->xpath($input);
        self::assertSame('vat', $xpath->evaluate('string(//line[@id="3"]/@type)'));
        self::assertSame('1', $xpath->evaluate('string(//line[@id="3"]/baseline)'));
    }

    public function testCalculatesTheVatWithThePercentageOnTheTransactionDate(): void
    {
        // VH was 19% before 2012-10-01 in the fixture.
        $input = JournalTransactionInput::create('MEMO', new DateTimeImmutable('2012-09-30'))
            ->credit('8000', '100.00', vatCode: $this->vatCode())
            ->debit('1300', '119.00', customer: '1000');

        self::assertTrue($input->balance()->isZero());
        self::assertSame('19.00', $this->xpath($input)->evaluate('string(//line[@id="1"]/vatvalue)'));
    }

    public function testRefusesATransactionThatDoesNotBalance(): void
    {
        $input = JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-09'))
            ->debit('4008', '435.55')
            ->credit('1300', '400.00');

        self::assertSame('-35.55', $input->balance()->value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not balance: the credit lines are 35.55 less than the debit lines');

        $input->toXml('1000');
    }

    public function testRefusesALineWithVatCodeWithoutVatAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line 1 has VAT code VH without VAT amount');

        JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-09'))
            ->credit('8000', '100.00', vatCode: 'VH')
            ->debit('1300', '100.00')
            ->toXml('1000');
    }

    public function testRefusesOneLine(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs at least two');

        JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-09'))
            ->line(DetailLineInput::debit('4008', '0.00'))
            ->toXml('1000');
    }

    public function testRefusesNoLines(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('add one with debit(), credit() or line()');

        JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-09'))->toXml('1000');
    }

    public function testRefusesMoreThanOneDim2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not customer and costCenter');

        JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-09'))
            ->debit('1300', '1.00', customer: '1000', costCenter: '00100');
    }

    public function testIsImmutable(): void
    {
        $input = JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-09'));
        $input->debit('4008', '1.00');
        $input->regime(Regime::Fiscal);

        self::assertSame([], $input->lines());
    }

    private function xpath(JournalTransactionInput $input): DOMXPath
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
