<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\SalesTransaction;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\SalesTransaction\SalesTransaction;
use SolveBeam\Twinfield\SalesTransaction\SalesTransactionInput;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\FailedTransaction;
use SolveBeam\Twinfield\Transaction\MatchStatus;
use SolveBeam\Twinfield\Transaction\SavedTransaction;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;
use SolveBeam\Twinfield\Transaction\TransactionStatus;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class SalesTransactionResourceTest extends TestCase
{
    public function testReadsATransaction(): void
    {
        $http = ReplayClient::fixture('sales-transaction-read');

        $transaction = $this->twinfield($http)->salesTransactions()->get('VRK', 202600012);

        self::assertSame(
            '<read><type>transaction</type><office>1000</office><code>VRK</code><number>202600012</number></read>',
            $this->xmlRequest($http->lastRequest()),
        );

        $header = $transaction->header;
        self::assertSame('VRK', $transaction->dayBook());
        self::assertSame(202600012, $transaction->number());
        self::assertSame(TransactionStatus::Final, $header->status);
        self::assertSame('2026/11', (string) $header->period);
        self::assertEquals(new DateTimeImmutable('2026-11-03 00:00:00'), $header->date);
        self::assertSame('manual', $header->origin);
        self::assertNull($header->originReference, 'Twinfield returns it empty.');
        self::assertSame('Order 778', $header->freeText1);
        self::assertNull($header->freeText3);

        self::assertSame('2026-0042', $transaction->invoiceNumber);
        self::assertEquals(new DateTimeImmutable('2026-12-03 00:00:00'), $transaction->dueDate);
        self::assertNull($transaction->paymentReference);

        $total = $transaction->total;
        self::assertSame('1300', $total->account->code);
        self::assertSame('BAS', $total->account->type);
        self::assertSame('1000', $transaction->customer()->code);
        self::assertSame('Example customer', $transaction->customer()->name);
        self::assertSame('DEB', $transaction->customer()->type);
        self::assertSame(DebitCredit::Debit, $total->debitCredit);
        self::assertFalse($transaction->isCreditNote());
        self::assertSame('181.50', $transaction->value()->value);
        self::assertSame('31.50', $total->vatTotal?->value);
        self::assertSame(MatchStatus::Available, $total->matchStatus);
        self::assertFalse($transaction->isPaid());
        self::assertSame(2, $total->matchLevel);
        self::assertSame(2, $total->relation);
        self::assertNull($total->openValue, 'Live responses only have basevalueopen.');
        self::assertSame('181.50', $total->openBaseValue?->value);
        self::assertSame([], $total->matches);

        self::assertCount(2, $transaction->details);
        [$consultancy, $training] = $transaction->details;
        self::assertSame(DebitCredit::Credit, $consultancy->debitCredit);
        self::assertSame('100.00', $consultancy->amounts->value->value);
        self::assertSame('VH', $consultancy->vatCode);
        self::assertSame('21.00', $consultancy->vatValue?->value);
        self::assertSame(MatchStatus::NotMatchable, $consultancy->matchStatus);
        $costCenter = $training->dim2;
        self::assertNotNull($costCenter);
        self::assertSame('00100', $costCenter->code);
        self::assertSame('KPL', $costCenter->type);
        self::assertSame('P0001', $training->dim3?->code);

        self::assertCount(1, $transaction->vat);
        $vat = $transaction->vat[0];
        self::assertSame('1530', $vat->account->code);
        self::assertSame('31.50', $vat->amounts->value->value);
        self::assertSame('150.00', $vat->vatTurnover?->value);
        self::assertSame(1, $vat->baseline);
        self::assertSame(MatchStatus::Available, $vat->matchStatus, 'Live: available, not notmatchable as documented.');
    }

    public function testReadsAPaidCreditNoteInAForeignCurrency(): void
    {
        $transaction = $this->twinfield(ReplayClient::fixture('sales-transaction-read-credit-note'))
            ->salesTransactions()
            ->get('VRK', 202600013);

        self::assertTrue($transaction->isCreditNote());
        self::assertSame('-121.00', $transaction->value()->value);
        self::assertSame('USD', $transaction->header->currency);
        self::assertSame('f386393c-e4ba-439a-add4-3b366535d7bf', $transaction->header->originReference);
        self::assertSame('+++100/0160/01495+++', $transaction->paymentReference);

        $total = $transaction->total;
        self::assertSame('111.53', $total->amounts->baseValue->value);
        self::assertSame('0.921700000', $total->amounts->rate);
        self::assertSame('121.00', $total->amounts->repValue?->value);
        self::assertSame('19.36', $total->vatBaseTotal?->value);
        self::assertSame('21.00', $total->vatRepTotal?->value);
        self::assertSame('Credit note 2026-0043', $total->description);
        self::assertSame('N', $total->freeChar);
        self::assertSame('Returned goods', $total->comment);

        self::assertTrue($transaction->isPaid());
        self::assertSame('0.00', $total->openBaseValue?->value);
        self::assertCount(1, $total->matches);
        $set = $total->matches[0];
        self::assertSame(TransactionStatus::Final, $set->status);
        self::assertSame('121.00', $set->matchValue->value);
        self::assertSame('BNK', $set->lines[0]->dayBook);
        self::assertSame(202600050, $set->lines[0]->number);

        self::assertSame(DebitCredit::Debit, $transaction->details[0]->debitCredit);
        self::assertSame(DebitCredit::Debit, $transaction->vat[0]->debitCredit);
    }

    public function testReadsTheOpenAmountNamesOfTheDocumentation(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/sales-transaction-read.xml');
        $xml = str_replace(
            '&lt;basevalueopen&gt;181.50&lt;/basevalueopen&gt;',
            '&lt;openvalue&gt;181.50&lt;/openvalue&gt;&lt;openbasevalue&gt;181.50&lt;/openbasevalue&gt;'
                . '&lt;openrepvalue&gt;234.79&lt;/openrepvalue&gt;',
            $xml,
        );

        $total = $this->twinfield(new ReplayClient(new Response(200, [], $xml)))
            ->salesTransactions()
            ->get('VRK', 202600012)
            ->total;

        self::assertSame('181.50', $total->openValue?->value);
        self::assertSame('181.50', $total->openBaseValue?->value);
        self::assertSame('234.79', $total->openRepValue?->value);
    }

    public function testThrowsWithoutInvoiceNumber(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/sales-transaction-read.xml');
        $xml = str_replace('&lt;invoicenumber&gt;2026-0042&lt;/invoicenumber&gt;', '', $xml);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('invoicenumber');

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->salesTransactions()->get('VRK', 202600012);
    }

    public function testThrowsWithoutDueDate(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/sales-transaction-read.xml');
        $xml = str_replace('&lt;duedate&gt;20261203&lt;/duedate&gt;', '', $xml);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('duedate');

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->salesTransactions()->get('VRK', 202600012);
    }

    public function testThrowsWithoutCustomer(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/sales-transaction-read.xml');
        $xml = preg_replace('~&lt;dim2 name="Example customer".*?&lt;/dim2&gt;~', '', $xml, 1);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dim2 is missing or empty');

        $this->twinfield(new ReplayClient(new Response(200, [], (string) $xml)))->salesTransactions()->get('VRK', 202600012);
    }

    public function testEditKeepsAllValues(): void
    {
        $transaction = $this->twinfield(ReplayClient::fixture('sales-transaction-read-credit-note'))
            ->salesTransactions()
            ->get('VRK', 202600013);

        $input = $transaction->edit();

        self::assertTrue($input->isCreditNote());
        self::assertSame(202600013, $input->transactionNumber());
        self::assertSame($transaction->value()->value, $input->total()->value);

        $xpath = $this->xpath($input->toXml('1000')->toXml());
        self::assertSame('202600013', $xpath->evaluate('string(//header/number)'));
        self::assertSame('2026/11', $xpath->evaluate('string(//header/period)'));
        self::assertSame('USD', $xpath->evaluate('string(//header/currency)'));
        self::assertSame('20261205', $xpath->evaluate('string(//header/duedate)'));
        self::assertSame('2026-0043', $xpath->evaluate('string(//header/invoicenumber)'));
        self::assertSame('+++100/0160/01495+++', $xpath->evaluate('string(//header/paymentreference)'));
        self::assertSame('1300', $xpath->evaluate('string(//line[@type="total"]/dim1)'));
        self::assertSame('1000', $xpath->evaluate('string(//line[@type="total"]/dim2)'));
        self::assertSame('credit', $xpath->evaluate('string(//line[@type="total"]/debitcredit)'));
        self::assertSame('121.00', $xpath->evaluate('string(//line[@type="total"]/value)'));
        self::assertSame('Credit note 2026-0043', $xpath->evaluate('string(//line[@type="total"]/description)'));
        self::assertSame('N', $xpath->evaluate('string(//line[@type="total"]/freechar)'));
        self::assertSame('Returned goods', $xpath->evaluate('string(//line[@type="total"]/comment)'));
        self::assertSame('debit', $xpath->evaluate('string(//line[@id="2"]/debitcredit)'));
        self::assertSame('vat', $xpath->evaluate('string(//line[@id="3"]/@type)'));
        self::assertSame(0.0, $xpath->evaluate('count(//basevalue)'), 'Twinfield calculates base amounts again.');
        self::assertSame(0.0, $xpath->evaluate('count(//matches)'), 'Matches are not allowed in a request.');
    }

    public function testCreatesATransaction(): void
    {
        $http = ReplayClient::fixture('sales-transaction-save');

        $transaction = $this->twinfield($http)->salesTransactions()->create(
            SalesTransactionInput::invoice('VRK', '1000', '2026-0044', new DateTimeImmutable('2026-11-09'))
                ->dueDate(new DateTimeImmutable('2026-12-09'))
                ->paymentReference('2026-0044')
                ->revenue('8000', '100.00', vatCode: 'VH', vatValue: '21.00', description: 'Consultancy'),
        );

        self::assertStringStartsWith(
            '<transaction destiny="temporary"><header><office>1000</office><code>VRK</code>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertInstanceOf(SalesTransaction::class, $transaction);
        self::assertSame(202600014, $transaction->number());
        self::assertSame(TransactionStatus::Provisional, $transaction->header->status);
        self::assertSame('121.00', $transaction->value()->value);
        self::assertSame('121.00', $transaction->total->openBaseValue?->value);
    }

    public function testCreateRefusesATransactionNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('use update()');

        $this->twinfield(new ReplayClient())->salesTransactions()->create(
            SalesTransactionInput::invoice('VRK', '1000', '2026-0044', new DateTimeImmutable('2026-11-09'))
                ->number(202600014)
                ->revenue('8000', '1.00'),
        );
    }

    public function testUpdateReadsFirst(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('sales-transaction-read'),
            ReplayClient::response('sales-transaction-read'),
            ReplayClient::response('sales-transaction-read'),
        );
        $sales = $this->twinfield($http)->salesTransactions();

        $sales->update($sales->get('VRK', 202600012)->edit()->paymentReference('2026-0042'));

        self::assertCount(3, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[1]));
        self::assertStringContainsString(
            '<paymentreference>2026-0042</paymentreference>',
            $this->xmlRequest($http->requests[2]),
        );
    }

    public function testUpdateThrowsNotFound(): void
    {
        $this->expectException(TransactionNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('bank-transaction-read-not-found'))->salesTransactions()->update(
            SalesTransactionInput::invoice('VRK', '1000', '2026-0044', new DateTimeImmutable('2026-11-09'))
                ->number(202699999)
                ->revenue('8000', '1.00'),
        );
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $sales = $this->twinfield(ReplayClient::fixture('bank-transaction-read-not-found'))->salesTransactions();

        self::assertNull($sales->find('VRK', 202699999));
    }

    public function testSaveManyReportsPerTransaction(): void
    {
        $http = ReplayClient::fixture('sales-transaction-save-many-partial');
        $date = new DateTimeImmutable('2026-11-09');

        $result = $this->twinfield($http)->salesTransactions()->saveMany(
            SalesTransactionInput::invoice('VRK', '1000', '2026-0045', $date)->revenue('8000', '50.00', 'VH', '10.50'),
            SalesTransactionInput::invoice('VRK', '9999', '2026-0046', $date)->revenue('8000', '50.00', 'VH', '10.50'),
        );

        self::assertStringStartsWith('<transactions><transaction destiny="temporary">', $this->xmlRequest($http->lastRequest()));
        self::assertFalse($result->allSaved());

        $saved = $result->saved()[0];
        self::assertInstanceOf(SavedTransaction::class, $saved);
        self::assertInstanceOf(SalesTransaction::class, $saved->transaction);
        self::assertSame('2026-0045', $saved->transaction->invoiceNumber);

        $failed = $result->failed()[0];
        self::assertInstanceOf(FailedTransaction::class, $failed);
        self::assertSame('transactions/transaction[2]/lines/line[1]/dim2', $failed->errors[0]->path);
        self::assertSame('Relatie 9999 bestaat niet.', $failed->errors[0]->text);
        self::assertSame('Factuurnummer 2026-0046 is al gebruikt.', $failed->warnings[0]->text);
    }

    public function testPostsATransaction(): void
    {
        $http = ReplayClient::fixture('sales-transaction-post');

        $this->twinfield($http)->salesTransactions()->post('VRK', 202600014);

        self::assertSame(
            '<transaction action="post"><office>1000</office><code>VRK</code><number>202600014</number></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeletesATransaction(): void
    {
        $http = ReplayClient::fixture('sales-transaction-delete');

        $this->twinfield($http)->salesTransactions()->delete('VRK', 202600014, 'Booked twice');

        self::assertSame(
            '<transaction action="delete" reason="Booked twice"><office>1000</office><code>VRK</code>'
            . '<number>202600014</number></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testExcludesFromCollection(): void
    {
        $http = ReplayClient::fixture('sales-transaction-change-pay-status');

        $this->twinfield($http)->salesTransactions()->excludeFromCollection('VRK', 202600012);

        self::assertSame(
            '<transaction action="changepaystatus"><office>1000</office><code>VRK</code>'
            . '<number>202600012</number><paystatus>N</paystatus></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testIncludesInCollection(): void
    {
        $http = ReplayClient::fixture('sales-transaction-change-pay-status');

        $this->twinfield($http)->salesTransactions()->includeInCollection('VRK', 202600012);

        self::assertSame(
            '<transaction action="changepaystatus"><office>1000</office><code>VRK</code>'
            . '<number>202600012</number></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testSearchesOpenInvoices(): void
    {
        $http = ReplayClient::fixture('finder-open-invoices');

        $page = $this->twinfield($http)->salesTransactions()->openInvoices()
            ->pattern('2026-*')
            ->customer('1000')
            ->account('1300')
            ->value('181.50')
            ->openValue('181.50')
            ->limit(10)
            ->get();

        $body = (string) preg_replace('~>\s+<~', '><', (string) $http->lastRequest()->getBody());
        self::assertStringContainsString('<type>IVT</type>', $body);
        self::assertStringContainsString('<pattern>2026-*</pattern>', $body);
        self::assertStringContainsString('<maxRows>10</maxRows>', $body);

        foreach (['dim2' => '1000', 'dim1' => '1300', 'value' => '181.50', 'openvalue' => '181.50'] as $name => $value) {
            self::assertStringContainsString(
                sprintf('<ArrayOfString><string>%s</string><string>%s</string></ArrayOfString>', $name, $value),
                $body,
            );
        }

        self::assertSame(3, $page->totalRows);
        [$invoice, $creditNote, $purchase] = $page->items;
        self::assertSame('2026-0042', $invoice->invoiceNumber);
        self::assertSame('181.50', $invoice->amount->value);
        self::assertSame('1000', $invoice->relation);
        self::assertSame('Example customer', $invoice->relationName);
        self::assertSame(DebitCredit::Credit, $invoice->debitCredit);
        self::assertSame(DebitCredit::Debit, $creditNote->debitCredit);
        self::assertSame('2000', $purchase->relation);
    }

    public function testOpenInvoicesRefusesAnUnexpectedRow(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/finder-open-invoices.xml');
        $xml = str_replace('<string>C</string>', '<string>X</string>', $xml);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('debit/credit is not D or C');

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->salesTransactions()->openInvoices()->get();
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(new StaticAccessTokenProvider('token-123', 'https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Sales transactions belong to a company');

        $twinfield->salesTransactions()->get('VRK', 202600012);
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(new StaticAccessTokenProvider('token-123', 'https://example.test'), $http, $psr17, $psr17)->company('1000');
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
