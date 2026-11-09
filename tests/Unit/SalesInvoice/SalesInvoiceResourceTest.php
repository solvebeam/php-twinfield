<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\SalesInvoice;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\SalesInvoice\PaymentMethod;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceInput;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceNotFoundException;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceStatus;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class SalesInvoiceResourceTest extends TestCase
{
    public function testReadsAConceptInvoice(): void
    {
        $http = ReplayClient::fixture('sales-invoice-read-concept');

        $invoice = $this->twinfield($http)->salesInvoices()->get('FACTUUR', 12);

        self::assertSame(
            '<read><type>salesinvoice</type><office>1000</office><code>FACTUUR</code>'
                . '<invoicenumber>12</invoicenumber></read>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertSame('1000', $invoice->company->code);
        self::assertSame('FACTUUR', $invoice->invoiceType);
        self::assertSame(12, $invoice->invoiceNumber);
        self::assertEquals(new DateTimeImmutable('2026-11-03 00:00:00'), $invoice->invoiceDate);
        self::assertEquals(new DateTimeImmutable('2026-12-03 00:00:00'), $invoice->dueDate);
        self::assertNull($invoice->performanceDate, 'Live responses leave it out.');
        self::assertSame('BNK', $invoice->bank);
        self::assertSame('1000', $invoice->customer);
        self::assertSame('2026/11', (string) $invoice->period);
        self::assertSame('EUR', $invoice->currency);
        self::assertSame(SalesInvoiceStatus::Concept, $invoice->status);
        self::assertFalse($invoice->isFinal());
        self::assertSame(PaymentMethod::Bank, $invoice->paymentMethod);
        self::assertSame(1, $invoice->invoiceAddressNumber);
        self::assertSame(1, $invoice->deliverAddressNumber);
        self::assertNull($invoice->headerText);
        self::assertNull($invoice->footerText);
        self::assertNull($invoice->transaction);

        self::assertCount(1, $invoice->lines);
        $line = $invoice->lines[0];
        self::assertSame(1, $line->id);
        self::assertSame('1', $line->article);
        self::assertSame('1', $line->subarticle);
        self::assertSame('1', $line->quantity);
        self::assertSame(1, $line->units);
        self::assertSame('125.00', $line->unitPriceExcl?->value);
        self::assertNull($line->unitPriceInc);
        self::assertSame('VH', $line->vatCode);
        self::assertTrue($line->allowDiscountOrPremium);
        self::assertSame('Monthly fee as agreed', $line->description);
        self::assertNull($line->performance);
        self::assertNull($line->freeText1, 'Empty, with empty attributes.');
        self::assertNull($line->freeText3);
        self::assertSame('8000', $line->account->code);
        self::assertNull($line->account->name, 'Live responses have no attributes on dim1.');
        self::assertSame('125.00', $line->valueExcl->value);
        self::assertSame('26.25', $line->vatValue->value);
        self::assertSame('151.25', $line->valueInc->value);
        self::assertFalse($line->isOneOff());

        self::assertSame('125.00', $invoice->totals->valueExcl->value);
        self::assertSame('151.25', $invoice->totals->valueInc->value);
        self::assertSame('26.25', $invoice->totals->vat()->value);

        self::assertCount(1, $invoice->vatLines);
        self::assertSame('VH', $invoice->vatLines[0]->vatCode);
        self::assertSame('BTW 21%', $invoice->vatLines[0]->vatName);
        self::assertSame('26.25', $invoice->vatLines[0]->vatValue->value);
        self::assertNull($invoice->vatLines[0]->performance, 'Empty performance elements.');
    }

    public function testReadsAFinalInvoiceWithItsTransaction(): void
    {
        $invoice = $this->twinfield(ReplayClient::fixture('sales-invoice-read-final'))
            ->salesInvoices()
            ->get('FACTUUR', 5);

        self::assertSame(SalesInvoiceStatus::Final, $invoice->status);
        self::assertTrue($invoice->isFinal());
        self::assertSame(PaymentMethod::Cash, $invoice->paymentMethod);
        self::assertSame('VRK', $invoice->transaction?->dayBook);
        self::assertSame(202600012, $invoice->transaction->number);
        self::assertCount(2, $invoice->lines);
        self::assertSame('181.50', $invoice->totals->valueInc->value);
    }

    public function testThrowsNotFound(): void
    {
        $this->expectException(SalesInvoiceNotFoundException::class);
        $this->expectExceptionMessage('Sales invoice FACTUUR 999999999 not found: Factuur 999999999 van type FACTUUR bestaat niet.');

        $this->twinfield(ReplayClient::fixture('sales-invoice-not-found'))->salesInvoices()->get('FACTUUR', 999999999);
    }

    public function testFindGivesNullWhenNotFound(): void
    {
        self::assertNull(
            $this->twinfield(ReplayClient::fixture('sales-invoice-not-found'))->salesInvoices()->find('FACTUUR', 999999999),
        );
    }

    public function testThrowsWhenAFinalInvoiceHasNoTransaction(): void
    {
        $xml = $this->fixture('sales-invoice-read-final');
        $xml = (string) preg_replace('~&lt;financials&gt;.*?&lt;/financials&gt;~', '', $xml);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('is final without a sales transaction');

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->salesInvoices()->get('FACTUUR', 5);
    }

    public function testThrowsOnAnUnknownStatus(): void
    {
        $xml = str_replace('&lt;status&gt;concept&lt;', '&lt;status&gt;archived&lt;', $this->fixture('sales-invoice-read-concept'));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('salesinvoice/header/status');

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->salesInvoices()->get('FACTUUR', 12);
    }

    public function testThrowsOnALineWithoutPrice(): void
    {
        $xml = str_replace(
            '&lt;unitspriceexcl&gt;125.00&lt;/unitspriceexcl&gt;',
            '',
            $this->fixture('sales-invoice-read-concept'),
        );

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('salesinvoice/lines/line: Sales invoice line 1 must have a unit price');

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->salesInvoices()->get('FACTUUR', 12);
    }

    public function testCreatesAnInvoiceAndReturnsTheSavedOne(): void
    {
        $http = ReplayClient::fixture('sales-invoice-save');

        $invoice = $this->twinfield($http)->salesInvoices()->create(
            SalesInvoiceInput::create('FACTUUR', '1000')
                ->paymentMethod(PaymentMethod::Bank)
                ->article('510', quantity: '2', subarticle: '501.01'),
        );

        self::assertCount(1, $http->requests, 'No read before or after: the response has the invoice.');
        self::assertSame(
            '<salesinvoice><header><office>1000</office><invoicetype>FACTUUR</invoicetype><customer>1000</customer>'
                . '<status>concept</status><paymentmethod>bank</paymentmethod></header><lines><line id="1">'
                . '<article>510</article><subarticle>501.01</subarticle><quantity>2</quantity></line></lines></salesinvoice>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertSame(13, $invoice->invoiceNumber);
        self::assertSame('2.00', $invoice->lines[0]->quantity);
        self::assertSame('Omzet', $invoice->lines[0]->account->name);
        self::assertSame('101.57', $invoice->totals->valueInc->value);
    }

    public function testCreateRefusesAnInputWithNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('use update()');

        $this->twinfield(new ReplayClient())->salesInvoices()->create(
            SalesInvoiceInput::existing('FACTUUR', 12, '1000')->article('510'),
        );
    }

    public function testThrowsTheRefusalOfTwinfield(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('sales-invoice-save-error'))->salesInvoices()->create(
                SalesInvoiceInput::create('FACTUUR', '9999')->article('510', 2),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            $errors = $exception->result->errors();
            self::assertCount(1, $errors);
            self::assertSame('Debiteur 9999 bestaat niet.', $errors[0]->text);
        }
    }

    public function testUpdatesAConceptInvoice(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('sales-invoice-read-concept'),
            ReplayClient::response('sales-invoice-read-concept'),
            ReplayClient::response('sales-invoice-save'),
        );
        $invoices = $this->twinfield($http)->salesInvoices();

        $invoices->update($invoices->get('FACTUUR', 12)->edit()->footerText('Thank you!'));

        self::assertCount(3, $http->requests, 'Read, check it is a concept, save.');
        $xpath = $this->xpath($this->xmlRequest($http->lastRequest()));
        self::assertSame('12', $xpath->evaluate('string(/salesinvoice/header/invoicenumber)'));
        self::assertSame('Thank you!', $xpath->evaluate('string(/salesinvoice/header/footertext)'));
        self::assertSame('1', $xpath->evaluate('string(/salesinvoice/lines/line/@id)'));
    }

    public function testUpdateRefusesAFinalInvoice(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Sales invoice FACTUUR 5 is final: only concept invoices can be changed.');

        $this->twinfield(ReplayClient::fixture('sales-invoice-read-final'))->salesInvoices()->update(
            SalesInvoiceInput::existing('FACTUUR', 5, '1000')->article('1'),
        );
    }

    public function testUpdateRefusesAnInputWithoutNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield(new ReplayClient())->salesInvoices()->update(SalesInvoiceInput::create('FACTUUR', '1000'));
    }

    public function testCalculatesWithoutSaving(): void
    {
        $http = ReplayClient::fixture('sales-invoice-calculate');

        $calculation = $this->twinfield($http)->salesInvoices()->calculate(
            SalesInvoiceInput::create('FACTUUR', '1000')->article('510', '2', '501.01'),
        );

        $xpath = $this->xpath($this->xmlRequest($http->lastRequest()));
        self::assertSame('true', $xpath->evaluate('string(/salesinvoice/@calculateonly)'));
        self::assertSame('83.94', $calculation->totals->valueExcl->value);
        self::assertSame('17.63', $calculation->totals->vat()->value);
        self::assertSame('101.57', $calculation->lines[0]->valueInc->value);
        self::assertCount(1, $calculation->vatLines);
    }

    public function testFinalizesAConceptInvoice(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('sales-invoice-read-concept'),
            ReplayClient::response('sales-invoice-save-final'),
        );

        $invoice = $this->twinfield($http)->salesInvoices()->finalize('FACTUUR', 12);

        $xpath = $this->xpath($this->xmlRequest($http->lastRequest()));
        self::assertSame('final', $xpath->evaluate('string(/salesinvoice/header/status)'));
        self::assertSame('12', $xpath->evaluate('string(/salesinvoice/header/invoicenumber)'));
        self::assertTrue($invoice->isFinal());
        self::assertSame(202600013, $invoice->transaction?->number);
    }

    public function testFinalizeRefusesAFinalInvoice(): void
    {
        $this->expectException(LogicException::class);

        $this->twinfield(ReplayClient::fixture('sales-invoice-read-final'))->salesInvoices()->finalize('FACTUUR', 5);
    }

    public function testReadsTheBookedSalesTransaction(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('sales-invoice-read-final'),
            ReplayClient::response('sales-transaction-read'),
        );
        $invoices = $this->twinfield($http)->salesInvoices();

        $transaction = $invoices->salesTransaction($invoices->get('FACTUUR', 5));

        self::assertSame(
            '<read><type>transaction</type><office>1000</office><code>VRK</code><number>202600012</number></read>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame('181.50', $transaction->value()->value);
    }

    public function testAConceptInvoiceHasNoSalesTransaction(): void
    {
        $invoices = $this->twinfield(ReplayClient::fixture('sales-invoice-read-concept'))->salesInvoices();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('only a final invoice has a sales transaction');

        $invoices->salesTransaction($invoices->get('FACTUUR', 12));
    }

    public function testSearchesInvoiceTypes(): void
    {
        $http = ReplayClient::fixture('finder-invoice-types');

        $page = $this->twinfield($http)->salesInvoices()->invoiceTypes()->get();

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertStringContainsString('<type>INV</type>', (string) $http->lastRequest()->getBody());
        self::assertSame(['FACTUUR', 'UREN'], array_map(static fn ($type): string => $type->code, $page->items));
        self::assertSame('Uren factuur', $page->items[1]->name);
        self::assertSame('1000', $page->items[0]->company->code);
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);

        $twinfield->salesInvoices()->get('FACTUUR', 1);
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/' . $name . '.xml');
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML($xml);

        return new DOMXPath($document);
    }

    private function xmlRequest(RequestInterface $request): string
    {
        $document = new DOMDocument();
        $document->loadXML((string) $request->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        $xmlRequest = $xpath->evaluate('string(//tw:ProcessXmlString/tw:xmlRequest)');

        self::assertIsString($xmlRequest);

        return $xmlRequest;
    }
}
