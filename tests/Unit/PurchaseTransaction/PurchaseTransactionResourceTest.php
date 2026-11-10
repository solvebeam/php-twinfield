<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\PurchaseTransaction;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\PurchaseTransaction\PurchaseTransaction;
use SolveBeam\Twinfield\PurchaseTransaction\PurchaseTransactionInput;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\FailedTransaction;
use SolveBeam\Twinfield\Transaction\MatchStatus;
use SolveBeam\Twinfield\Transaction\SavedTransaction;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;
use SolveBeam\Twinfield\Transaction\TransactionStatus;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class PurchaseTransactionResourceTest extends TestCase
{
    public function testReadsATransaction(): void
    {
        $http = ReplayClient::fixture('purchase-transaction-read');

        $transaction = $this->twinfield($http)->purchaseTransactions()->get('INK', 202600012);

        self::assertSame(
            '<read><type>transaction</type><office>1000</office><code>INK</code><number>202600012</number></read>',
            $this->xmlRequest($http->lastRequest()),
        );

        $header = $transaction->header;
        self::assertSame('INK', $transaction->dayBook());
        self::assertSame(202600012, $transaction->number());
        self::assertSame(TransactionStatus::Final, $header->status);
        self::assertSame('2026/11', (string) $header->period);
        self::assertEquals(new DateTimeImmutable('2026-11-03 00:00:00'), $header->date);
        self::assertSame('manual', $header->origin);
        self::assertNull($header->originReference, 'Twinfield returns it empty.');
        self::assertSame('Order 778', $header->freeText1);
        self::assertNull($header->freeText3);

        self::assertSame('F-2026-0042', $transaction->invoiceNumber);
        self::assertEquals(new DateTimeImmutable('2026-12-03 00:00:00'), $transaction->dueDate);
        self::assertNull($transaction->paymentReference);

        $total = $transaction->total;
        self::assertSame('1600', $total->account->code);
        self::assertSame('BAS', $total->account->type);
        self::assertSame('2000', $transaction->supplier()->code);
        self::assertSame('Example supplier', $transaction->supplier()->name);
        self::assertSame('CRD', $transaction->supplier()->type);
        self::assertSame(DebitCredit::Credit, $total->debitCredit);
        self::assertFalse($transaction->isCreditNote());
        self::assertSame('181.50', $transaction->value()->value);
        self::assertSame('31.50', $total->vatTotal?->value);
        self::assertSame(MatchStatus::Available, $total->matchStatus);
        self::assertFalse($transaction->isPaid());
        self::assertSame(2, $total->matchLevel);
        self::assertSame(2, $total->relation);
        self::assertNull($total->openValue, 'Live responses only have basevalueopen.');
        self::assertSame('-181.50', $total->openBaseValue?->value, 'Signed: negative for a credit total line.');
        self::assertNull($total->matchDate);
        self::assertSame([], $total->matches);

        self::assertCount(2, $transaction->details);
        [$consultancy, $training] = $transaction->details;
        self::assertSame(DebitCredit::Debit, $consultancy->debitCredit);
        self::assertSame('100.00', $consultancy->value->value);
        self::assertSame('IH', $consultancy->vatCode);
        self::assertSame('21.00', $consultancy->vatValue?->value);
        self::assertSame(MatchStatus::NotMatchable, $consultancy->matchStatus);
        $costCenter = $training->dim2;
        self::assertNotNull($costCenter);
        self::assertSame('00100', $costCenter->code);
        self::assertSame('KPL', $costCenter->type);
        self::assertSame('P0001', $training->dim3?->code);

        self::assertCount(1, $transaction->vat);
        $vat = $transaction->vat[0];
        self::assertSame('1520', $vat->account->code);
        self::assertSame('31.50', $vat->value->value);
        self::assertSame('150.00', $vat->vatTurnover?->value);
        self::assertSame(1, $vat->baseline);
        self::assertSame(MatchStatus::Available, $vat->matchStatus, 'Live: available, not notmatchable as documented.');
    }

    public function testReadsAPaidCreditNoteInAForeignCurrency(): void
    {
        $transaction = $this->twinfield(ReplayClient::fixture('purchase-transaction-read-credit-note'))
            ->purchaseTransactions()
            ->get('INK', 202600013);

        self::assertTrue($transaction->isCreditNote());
        self::assertSame('-121.00', $transaction->value()->value);
        self::assertSame('USD', $transaction->header->currency);
        self::assertSame('f386393c-e4ba-439a-add4-3b366535d7bf', $transaction->header->originReference);
        self::assertSame('+++100/0160/01495+++', $transaction->paymentReference);

        $total = $transaction->total;
        self::assertSame('111.53', $total->baseValue->value);
        self::assertSame('0.921700000', $total->rate);
        self::assertSame('121.00', $total->repValue?->value);
        self::assertSame('19.36', $total->vatBaseTotal?->value);
        self::assertSame('21.00', $total->vatRepTotal?->value);
        self::assertSame('Credit note F-2026-0043', $total->description);
        self::assertSame('N', $total->freeChar);
        self::assertSame('Returned goods', $total->comment);

        self::assertTrue($transaction->isPaid());
        self::assertEquals(new DateTimeImmutable('2026-11-06 00:00:00'), $total->matchDate);
        self::assertSame('0.00', $total->openBaseValue?->value);
        self::assertCount(1, $total->matches);
        $set = $total->matches[0];
        self::assertSame(TransactionStatus::Final, $set->status);
        self::assertSame('121.00', $set->matchValue->value);
        self::assertSame('BNK', $set->lines[0]->dayBook);
        self::assertSame(202600050, $set->lines[0]->number);

        self::assertSame(DebitCredit::Credit, $transaction->details[0]->debitCredit);
        self::assertSame(DebitCredit::Credit, $transaction->vat[0]->debitCredit);
    }

    public function testReadsTheOpenAmountNamesOfTheDocumentation(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/purchase-transaction-read.xml');
        $xml = str_replace(
            '&lt;basevalueopen&gt;-181.50&lt;/basevalueopen&gt;',
            '&lt;openvalue&gt;181.50&lt;/openvalue&gt;&lt;openbasevalue&gt;181.50&lt;/openbasevalue&gt;'
                . '&lt;openrepvalue&gt;234.79&lt;/openrepvalue&gt;',
            $xml,
        );

        $total = $this->twinfield(new ReplayClient(new Response(200, [], $xml)))
            ->purchaseTransactions()
            ->get('INK', 202600012)
            ->total;

        self::assertSame('181.50', $total->openValue?->value);
        self::assertSame('181.50', $total->openBaseValue?->value);
        self::assertSame('234.79', $total->openRepValue?->value);
    }

    public function testThrowsWithoutInvoiceNumber(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/purchase-transaction-read.xml');
        $xml = str_replace('&lt;invoicenumber&gt;F-2026-0042&lt;/invoicenumber&gt;', '', $xml);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('invoicenumber');

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->purchaseTransactions()->get('INK', 202600012);
    }

    public function testReadsWithoutDueDate(): void
    {
        // Live: a purchase invoice entered by hand can be without due date.
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/purchase-transaction-read.xml');
        $xml = str_replace('&lt;duedate&gt;20261203&lt;/duedate&gt;', '', $xml);

        $transaction = $this->twinfield(new ReplayClient(new Response(200, [], $xml)))
            ->purchaseTransactions()
            ->get('INK', 202600012);

        self::assertNull($transaction->dueDate);
        self::assertSame(0.0, $this->xpath($transaction->edit()->toXml('1000')->toXml())->evaluate('count(//duedate)'));
    }

    public function testEditKeepsANegativeDiscountLine(): void
    {
        // Live, imported purchase invoices have discount lines with a negative amount on the debit side.
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/purchase-transaction-read.xml');
        $xml = str_replace(
            '&lt;basevalue&gt;50.00&lt;/basevalue&gt;&lt;rate&gt;1&lt;/rate&gt;&lt;value&gt;50.00&lt;/value&gt;',
            '&lt;basevalue&gt;-50.00&lt;/basevalue&gt;&lt;rate&gt;1&lt;/rate&gt;&lt;value&gt;-50.00&lt;/value&gt;',
            $xml,
            $count,
        );
        self::assertSame(1, $count);

        $transaction = $this->twinfield(new ReplayClient(new Response(200, [], $xml)))
            ->purchaseTransactions()
            ->get('INK', 202600012);

        self::assertSame('-50.00', $transaction->details[1]->value->value);

        $xpath = $this->xpath($transaction->edit()->toXml('1000')->toXml());
        self::assertSame('debit', $xpath->evaluate('string(//line[@id="3"]/debitcredit)'));
        self::assertSame('-50.00', $xpath->evaluate('string(//line[@id="3"]/value)'));
    }

    public function testThrowsWithoutSupplier(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/purchase-transaction-read.xml');
        $xml = preg_replace('~&lt;dim2 name="Example supplier".*?&lt;/dim2&gt;~', '', $xml, 1);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dim2 is missing or empty');

        $this->twinfield(new ReplayClient(new Response(200, [], (string) $xml)))->purchaseTransactions()->get('INK', 202600012);
    }

    public function testEditKeepsAllValues(): void
    {
        $transaction = $this->twinfield(ReplayClient::fixture('purchase-transaction-read-credit-note'))
            ->purchaseTransactions()
            ->get('INK', 202600013);

        $input = $transaction->edit();

        self::assertTrue($input->isCreditNote());
        self::assertSame(202600013, $input->transactionNumber());
        self::assertSame($transaction->value()->value, $input->total()->value);

        $xpath = $this->xpath($input->toXml('1000')->toXml());
        self::assertSame('202600013', $xpath->evaluate('string(//header/number)'));
        self::assertSame('2026/11', $xpath->evaluate('string(//header/period)'));
        self::assertSame('USD', $xpath->evaluate('string(//header/currency)'));
        self::assertSame('20261205', $xpath->evaluate('string(//header/duedate)'));
        self::assertSame('F-2026-0043', $xpath->evaluate('string(//header/invoicenumber)'));
        self::assertSame('+++100/0160/01495+++', $xpath->evaluate('string(//header/paymentreference)'));
        self::assertSame('1600', $xpath->evaluate('string(//line[@type="total"]/dim1)'));
        self::assertSame('2000', $xpath->evaluate('string(//line[@type="total"]/dim2)'));
        self::assertSame('debit', $xpath->evaluate('string(//line[@type="total"]/debitcredit)'));
        self::assertSame('121.00', $xpath->evaluate('string(//line[@type="total"]/value)'));
        self::assertSame('Credit note F-2026-0043', $xpath->evaluate('string(//line[@type="total"]/description)'));
        self::assertSame('N', $xpath->evaluate('string(//line[@type="total"]/freechar)'));
        self::assertSame('Returned goods', $xpath->evaluate('string(//line[@type="total"]/comment)'));
        self::assertSame('credit', $xpath->evaluate('string(//line[@id="2"]/debitcredit)'));
        self::assertSame('vat', $xpath->evaluate('string(//line[@id="3"]/@type)'));
        self::assertSame(0.0, $xpath->evaluate('count(//basevalue)'), 'Twinfield calculates base amounts again.');
        self::assertSame(0.0, $xpath->evaluate('count(//matches)'), 'Matches are not allowed in a request.');
    }

    public function testCreatesATransaction(): void
    {
        $http = ReplayClient::fixture('purchase-transaction-save');

        $transaction = $this->twinfield($http)->purchaseTransactions()->create(
            PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-0044', new DateTimeImmutable('2026-11-09'))
                ->dueDate(new DateTimeImmutable('2026-12-09'))
                ->paymentReference('F-2026-0044')
                ->expense('7000', '100.00', vatCode: 'IH', vatValue: '21.00', description: 'Consultancy'),
        );

        self::assertStringStartsWith(
            '<transaction destiny="temporary"><header><office>1000</office><code>INK</code>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertInstanceOf(PurchaseTransaction::class, $transaction);
        self::assertSame(202600014, $transaction->number());
        self::assertSame(TransactionStatus::Provisional, $transaction->header->status);
        self::assertSame('121.00', $transaction->value()->value);
        self::assertSame('-121.00', $transaction->total->openBaseValue?->value);
    }

    public function testCreateRefusesATransactionNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('use update()');

        $this->twinfield(new ReplayClient())->purchaseTransactions()->create(
            PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-0044', new DateTimeImmutable('2026-11-09'))
                ->number(202600014)
                ->expense('7000', '1.00'),
        );
    }

    public function testUpdateReadsFirst(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('purchase-transaction-read'),
            ReplayClient::response('purchase-transaction-read'),
            ReplayClient::response('purchase-transaction-read'),
        );
        $purchases = $this->twinfield($http)->purchaseTransactions();

        $purchases->update($purchases->get('INK', 202600012)->edit()->paymentReference('F-2026-0042'));

        self::assertCount(3, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[1]));
        self::assertStringContainsString(
            '<paymentreference>F-2026-0042</paymentreference>',
            $this->xmlRequest($http->requests[2]),
        );
    }

    public function testUpdateThrowsNotFound(): void
    {
        $this->expectException(TransactionNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('bank-transaction-read-not-found'))->purchaseTransactions()->update(
            PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-0044', new DateTimeImmutable('2026-11-09'))
                ->number(202699999)
                ->expense('7000', '1.00'),
        );
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $purchases = $this->twinfield(ReplayClient::fixture('bank-transaction-read-not-found'))->purchaseTransactions();

        self::assertNull($purchases->find('INK', 202699999));
    }

    public function testSaveManyReportsPerTransaction(): void
    {
        $http = ReplayClient::fixture('purchase-transaction-save-many-partial');
        $date = new DateTimeImmutable('2026-11-09');

        $result = $this->twinfield($http)->purchaseTransactions()->saveMany(
            PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-0045', $date)->expense('7000', '50.00', 'IH', '10.50'),
            PurchaseTransactionInput::invoice('INK', '9999', 'F-2026-0046', $date)->expense('7000', '50.00', 'IH', '10.50'),
        );

        self::assertStringStartsWith('<transactions><transaction destiny="temporary">', $this->xmlRequest($http->lastRequest()));
        self::assertFalse($result->allSaved());

        $saved = $result->saved()[0];
        self::assertInstanceOf(SavedTransaction::class, $saved);
        self::assertInstanceOf(PurchaseTransaction::class, $saved->transaction);
        self::assertSame('F-2026-0045', $saved->transaction->invoiceNumber);

        $failed = $result->failed()[0];
        self::assertInstanceOf(FailedTransaction::class, $failed);
        self::assertSame('transactions/transaction[2]/lines/line[1]/dim2', $failed->errors[0]->path);
        self::assertSame('Relatie 9999 bestaat niet.', $failed->errors[0]->text);
        self::assertSame('Factuurnummer F-2026-0046 is al gebruikt.', $failed->warnings[0]->text);
    }

    public function testPostsATransaction(): void
    {
        $http = ReplayClient::fixture('purchase-transaction-post');

        $this->twinfield($http)->purchaseTransactions()->post('INK', 202600014);

        self::assertSame(
            '<transaction action="post"><office>1000</office><code>INK</code><number>202600014</number></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeletesATransaction(): void
    {
        $http = ReplayClient::fixture('purchase-transaction-delete');

        $this->twinfield($http)->purchaseTransactions()->delete('INK', 202600014, 'Booked twice');

        self::assertSame(
            '<transaction action="delete" reason="Booked twice"><office>1000</office><code>INK</code>'
            . '<number>202600014</number></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testExcludesFromPaymentRuns(): void
    {
        $http = ReplayClient::fixture('purchase-transaction-change-pay-status');

        $this->twinfield($http)->purchaseTransactions()->excludeFromPaymentRuns('INK', 202600012);

        self::assertSame(
            '<transaction action="changepaystatus"><office>1000</office><code>INK</code>'
            . '<number>202600012</number><paystatus>N</paystatus></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testIncludesInPaymentRuns(): void
    {
        $http = ReplayClient::fixture('purchase-transaction-change-pay-status');

        $this->twinfield($http)->purchaseTransactions()->includeInPaymentRuns('INK', 202600012);

        self::assertSame(
            '<transaction action="changepaystatus"><office>1000</office><code>INK</code>'
            . '<number>202600012</number></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testSearchesOpenInvoicesOfASupplier(): void
    {
        $http = ReplayClient::fixture('finder-open-invoices');

        $page = $this->twinfield($http)->purchaseTransactions()->openInvoices()
            ->supplier('2000')
            ->account('1600')
            ->get();

        $body = (string) preg_replace('~>\\s+<~', '><', (string) $http->lastRequest()->getBody());
        self::assertStringContainsString('<type>IVT</type>', $body);

        foreach (['dim2' => '2000', 'dim1' => '1600'] as $name => $value) {
            self::assertStringContainsString(
                sprintf('<ArrayOfString><string>%s</string><string>%s</string></ArrayOfString>', $name, $value),
                $body,
            );
        }

        // The fixture has two sales invoices and one purchase invoice: a purchase invoice is D.
        $purchase = $page->items[2];
        self::assertSame('2000', $purchase->relation);
        self::assertSame(DebitCredit::Debit, $purchase->debitCredit);
    }

    public function testOpenInvoicesRefusesAnEmptySupplier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('supplier (dim2) must not be empty');

        $this->twinfield(new ReplayClient())->purchaseTransactions()->openInvoices()->supplier('');
    }


    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Purchase transactions belong to a company');

        $twinfield->purchaseTransactions()->get('INK', 202600012);
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
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
