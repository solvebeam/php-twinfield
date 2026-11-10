<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\SalesInvoice;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\SalesInvoice\PaymentMethod;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceInput;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceLineInput;
use SolveBeam\Twinfield\Transaction\Performance;

final class SalesInvoiceInputTest extends TestCase
{
    public function testWritesAllHeaderFieldsInTheDocumentedOrder(): void
    {
        $input = SalesInvoiceInput::create('FACTUUR', '1000')
            ->invoiceDate(new DateTimeImmutable('2026-11-09 14:30'))
            ->dueDate(new DateTimeImmutable('2026-12-09'))
            ->performanceDate(new DateTimeImmutable('2026-11-01'))
            ->bank('BNK')
            ->period('2026/11', raiseWarning: false)
            ->currency('EUR')
            ->paymentMethod(PaymentMethod::DocumentsAgainstAcceptance)
            ->invoiceAddress(1)
            ->deliveryAddress(2)
            ->headerText('Dear customer,')
            ->footerText('Thank you!')
            ->final()
            ->withoutWarnings()
            ->article('510');

        self::assertSame(
            '<salesinvoice raisewarning="false"><header><office>1000</office><invoicetype>FACTUUR</invoicetype>'
                . '<invoicedate>20261109</invoicedate><duedate>20261209</duedate><performancedate>20261101</performancedate>'
                . '<bank>BNK</bank><customer>1000</customer><period raisewarning="false">2026/11</period>'
                . '<currency>EUR</currency><status>final</status><paymentmethod>da</paymentmethod>'
                . '<invoiceaddressnumber>1</invoiceaddressnumber><deliveraddressnumber>2</deliveraddressnumber>'
                . '<headertext>Dear customer,</headertext><footertext>Thank you!</footertext></header>'
                . '<lines><line id="1"><article>510</article><quantity>1</quantity></line></lines></salesinvoice>',
            $this->xml($input),
        );
    }

    public function testWritesAllLineFields(): void
    {
        $line = SalesInvoiceLineInput::oneOff('Consultancy November')
            ->quantity('1.5')
            ->units(1)
            ->unitPriceExcl('950')
            ->vatCode('VH')
            ->allowDiscountOrPremium(false)
            ->performance(Performance::services(new DateTimeImmutable('2026-11-03')))
            ->freeText(1, 'Order 778')
            ->freeText(3, 'Project X')
            ->dim1('8020');

        self::assertSame(
            '<line id="1"><article>0</article><quantity>1.5</quantity><units>1</units>'
                . '<unitspriceexcl>950.00</unitspriceexcl><vatcode>VH</vatcode>'
                . '<allowdiscountorpremium>false</allowdiscountorpremium><description>Consultancy November</description>'
                . '<performancedate>20261103</performancedate><performancetype>services</performancetype>'
                . '<freetext1>Order 778</freetext1><freetext3>Project X</freetext3><dim1>8020</dim1></line>',
            $this->lineXml(SalesInvoiceInput::create('FACTUUR', '1000')->line($line)),
        );
    }

    public function testOneOffShortcut(): void
    {
        $input = SalesInvoiceInput::create('FACTUUR', '1000')
            ->oneOff('Training', '50.00', vatCode: 'VL', quantity: 3, dim1: '8010');

        self::assertSame(
            '<line id="1"><article>0</article><quantity>3</quantity><unitspriceexcl>50.00</unitspriceexcl>'
                . '<vatcode>VL</vatcode><description>Training</description><dim1>8010</dim1></line>',
            $this->lineXml($input),
        );
    }

    public function testAPriceIncludingVatReplacesThePriceExcludingVat(): void
    {
        $line = SalesInvoiceLineInput::article('510')->unitPriceExcl('10.00')->unitPriceInc('12.10');

        self::assertNull($line->unitPriceExcl);
        self::assertSame('12.10', $line->unitPriceInc?->value);
    }

    public function testNewLinesGetTheIdsAfterTheExistingLines(): void
    {
        $input = SalesInvoiceInput::existing('FACTUUR', 12, '1000')
            ->line(SalesInvoiceLineInput::article('1')->id(3))
            ->article('2')
            ->line(SalesInvoiceLineInput::article('3')->id(1))
            ->article('4');

        self::assertMatchesRegularExpression(
            '~<line id="3"><article>1</article>.*<line id="4"><article>2</article>.*'
                . '<line id="1"><article>3</article>.*<line id="5"><article>4</article>~',
            $this->xml($input),
        );
    }

    public function testRemovesALine(): void
    {
        $input = SalesInvoiceInput::existing('FACTUUR', 12, '1000')
            ->line(SalesInvoiceLineInput::article('1')->id(1))
            ->line(SalesInvoiceLineInput::article('2')->id(2))
            ->removeLine(1);

        self::assertCount(1, $input->lines);
        self::assertSame('2', $input->lines[0]->article);

        $this->expectException(InvalidArgumentException::class);

        $input->removeLine(7);
    }

    public function testCalculateOnlyAttribute(): void
    {
        $xml = SalesInvoiceInput::create('FACTUUR', '1000')->article('510')->toXml('1000', calculateOnly: true)->toXml();

        self::assertStringStartsWith('<salesinvoice calculateonly="true">', $xml);
    }

    public function testNeedsALine(): void
    {
        $this->expectException(LogicException::class);

        SalesInvoiceInput::create('FACTUUR', '1000')->toXml('1000');
    }

    public function testRefusesADescriptionForAnArticle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only allowed for the one-off item 0 or -');

        SalesInvoiceLineInput::article('510')->description('Trousers');
    }

    public function testRefusesALongDescription(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesInvoiceLineInput::oneOff(str_repeat('x', 111));
    }

    public function testRefusesALongFreeText(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesInvoiceLineInput::article('510')->freeText(2, str_repeat('x', 37));
    }

    public function testRefusesAnUnknownFreeTextField(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesInvoiceLineInput::article('510')->freeText(4, 'x');
    }

    public function testRefusesAnInvalidQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesInvoiceLineInput::article('510')->quantity('1,5');
    }

    public function testAcceptsANegativeQuantity(): void
    {
        self::assertSame('-2', SalesInvoiceLineInput::article('510')->quantity(-2)->quantity);
    }

    public function testRefusesAPriceWithMoreThanTwoDecimals(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesInvoiceLineInput::article('510')->unitPriceExcl('1.005');
    }

    public function testRefusesAPerformanceCountry(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesInvoiceLineInput::article('510')->performance(Performance::goods()->country('DE'));
    }

    public function testRefusesAnInvalidInvoiceType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesInvoiceInput::create('factuur', '1000');
    }

    public function testConceptIsTheDefault(): void
    {
        self::assertStringContainsString(
            '<status>concept</status>',
            $this->xml(SalesInvoiceInput::create('FACTUUR', '1000')->article('510')),
        );
        self::assertStringContainsString(
            '<status>concept</status>',
            $this->xml(SalesInvoiceInput::create('FACTUUR', '1000')->final()->concept()->article('510')),
        );
    }

    private function xml(SalesInvoiceInput $input): string
    {
        return $input->toXml('1000')->toXml();
    }

    private function lineXml(SalesInvoiceInput $input): string
    {
        $xml = $this->xml($input);

        self::assertSame(1, preg_match('~<line id="\d+">.*</line>~', $xml, $matches));

        return $matches[0];
    }
}
