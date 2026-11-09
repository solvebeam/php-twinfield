<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\JournalTransaction;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\JournalTransaction\JournalTransaction;
use SolveBeam\Twinfield\JournalTransaction\JournalTransactionInput;
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

final class JournalTransactionResourceTest extends TestCase
{
    public function testReadsATransaction(): void
    {
        $http = ReplayClient::fixture('journal-transaction-read');

        $transaction = $this->twinfield($http)->journalTransactions()->get('MEMO', 202600003);

        self::assertSame(
            '<read><type>transaction</type><office>1000</office><code>MEMO</code><number>202600003</number></read>',
            $this->xmlRequest($http->lastRequest()),
        );

        $header = $transaction->header;
        self::assertSame('MEMO', $transaction->dayBook());
        self::assertSame(202600003, $transaction->number());
        self::assertSame(TransactionStatus::Provisional, $header->status);
        self::assertSame('2026/11', (string) $header->period);
        self::assertEquals(new DateTimeImmutable('2026-11-30 00:00:00'), $header->date);
        self::assertSame('manual', $header->origin);
        self::assertSame('Private use November', $header->freeText1);
        self::assertNull($header->freeText3);
        self::assertSame(Regime::Generic, $transaction->regime);

        self::assertCount(3, $transaction->details);
        [$revenue, $reversal, $cost] = $transaction->details;
        self::assertSame(1, $revenue->id);
        self::assertSame('8199', $revenue->account->code);
        self::assertSame('PNL', $revenue->account->type);
        self::assertSame(DebitCredit::Credit, $revenue->debitCredit);
        self::assertSame('1000.00', $revenue->amounts->value->value);
        self::assertSame('PRIVE-H', $revenue->vatCode);
        self::assertSame('210.00', $revenue->vatValue?->value);
        self::assertNull($revenue->invoiceNumber, 'Twinfield returns it empty.');
        self::assertSame(MatchStatus::NotMatchable, $revenue->matchStatus);
        self::assertSame(DebitCredit::Debit, $reversal->debitCredit);
        $costCenter = $cost->dim2;
        self::assertNotNull($costCenter);
        self::assertSame('00100', $costCenter->code);
        self::assertSame('KPL', $costCenter->type);
        self::assertSame('P0001', $cost->dim3?->code);
        self::assertNull($cost->description);
        self::assertSame('VAT on private use', $cost->comment);

        self::assertCount(1, $transaction->vat);
        $vat = $transaction->vat[0];
        self::assertSame(4, $vat->id);
        self::assertSame('1618', $vat->account->code);
        self::assertSame(DebitCredit::Credit, $vat->debitCredit);
        self::assertSame('210.00', $vat->amounts->value->value);
        self::assertSame('1000.00', $vat->vatTurnover?->value);
        self::assertSame(1, $vat->baseline);

        self::assertSame('1210.00', $transaction->debit()->value);
        self::assertSame('1210.00', $transaction->credit()->value);
    }

    public function testThrowsNotFoundForADeletedTransaction(): void
    {
        // Twinfield returns a deleted transaction with location="deleted" and no lines.
        try {
            $this->twinfield(ReplayClient::fixture('journal-transaction-read-deleted'))
                ->journalTransactions()
                ->get('MEMO', 202600002);
            self::fail('Expected a TransactionNotFoundException.');
        } catch (TransactionNotFoundException $exception) {
            self::assertTrue($exception->deleted);
            self::assertSame('MEMO', $exception->dayBook);
            self::assertSame(202600002, $exception->number);
        }
    }

    public function testFindReturnsNullForADeletedTransaction(): void
    {
        $journal = $this->twinfield(ReplayClient::fixture('journal-transaction-read-deleted'))->journalTransactions();

        self::assertNull($journal->find('MEMO', 202600002));
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $journal = $this->twinfield(ReplayClient::fixture('bank-transaction-read-not-found'))->journalTransactions();

        self::assertNull($journal->find('MEMO', 202699999));
    }

    public function testThrowsOnATotalLine(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/journal-transaction-read.xml');
        $xml = str_replace('&lt;line type="detail" id="2"&gt;', '&lt;line type="total" id="2"&gt;', $xml);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('has a total line, expected none');

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->journalTransactions()->get('MEMO', 202600003);
    }

    public function testThrowsWithoutRegime(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/soap/journal-transaction-read.xml');
        $xml = str_replace('&lt;regime&gt;Generic&lt;/regime&gt;', '', $xml);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('regime');

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->journalTransactions()->get('MEMO', 202600003);
    }

    public function testEditKeepsAllValues(): void
    {
        $transaction = $this->twinfield(ReplayClient::fixture('journal-transaction-read'))
            ->journalTransactions()
            ->get('MEMO', 202600003);

        $input = $transaction->edit();

        self::assertSame(202600003, $input->transactionNumber());
        self::assertTrue($input->balance()->isZero());

        $xpath = $this->xpath($input->toXml('1000')->toXml());
        self::assertSame('202600003', $xpath->evaluate('string(//header/number)'));
        self::assertSame('2026/11', $xpath->evaluate('string(//header/period)'));
        self::assertSame('EUR', $xpath->evaluate('string(//header/currency)'));
        self::assertSame('Generic', $xpath->evaluate('string(//header/regime)'));
        self::assertSame('Private use November', $xpath->evaluate('string(//header/freetext1)'));
        self::assertSame(0.0, $xpath->evaluate('count(//line[@type="total"])'));
        self::assertSame('detail', $xpath->evaluate('string(//line[@id="1"]/@type)'));
        self::assertSame('PRIVE-H', $xpath->evaluate('string(//line[@id="1"]/vatcode)'));
        self::assertSame('00100', $xpath->evaluate('string(//line[@id="3"]/dim2)'));
        self::assertSame('vat', $xpath->evaluate('string(//line[@id="4"]/@type)'));
        self::assertSame(0.0, $xpath->evaluate('count(//basevalue)'), 'Twinfield calculates base amounts again.');
    }

    public function testCreatesATransaction(): void
    {
        $http = ReplayClient::fixture('journal-transaction-save');

        $transaction = $this->twinfield($http)->journalTransactions()->create(
            JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-04'))
                ->debit('4008', '435.55')
                ->credit('1300', '435.55', customer: '1000', invoice: '11001770', description: 'Invoice paid'),
        );

        self::assertStringStartsWith(
            '<transaction destiny="temporary"><header><office>1000</office><code>MEMO</code>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertInstanceOf(JournalTransaction::class, $transaction);
        self::assertSame(202600004, $transaction->number());
        self::assertSame(TransactionStatus::Provisional, $transaction->header->status);

        $payment = $transaction->details[1];
        $customer = $payment->dim2;
        self::assertNotNull($customer);
        self::assertSame('1000', $customer->code);
        self::assertSame('DEB', $customer->type);
        self::assertSame('11001770', $payment->invoiceNumber);
        self::assertSame(MatchStatus::Available, $payment->matchStatus);
        self::assertSame('-435.55', $payment->openBaseValue?->value);
        self::assertSame('-435.55', $payment->openRepValue?->value);
        self::assertSame('435.55', $payment->amounts->repValue?->value);
    }

    public function testCreateRefusesATransactionNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('use update()');

        $this->twinfield(new ReplayClient())->journalTransactions()->create(
            JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-04'))
                ->number(202600004)
                ->debit('4008', '1.00')
                ->credit('1300', '1.00'),
        );
    }

    public function testUpdateReadsFirst(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('journal-transaction-read'),
            ReplayClient::response('journal-transaction-read'),
            ReplayClient::response('journal-transaction-read'),
        );
        $journal = $this->twinfield($http)->journalTransactions();

        $journal->update($journal->get('MEMO', 202600003)->edit()->freeText(2, 'Checked'));

        self::assertCount(3, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[1]));
        self::assertStringContainsString('<freetext2>Checked</freetext2>', $this->xmlRequest($http->requests[2]));
    }

    public function testUpdateThrowsNotFoundForADeletedTransaction(): void
    {
        $this->expectException(TransactionNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('journal-transaction-read-deleted'))->journalTransactions()->update(
            JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-27'))
                ->number(202600002)
                ->debit('4008', '1.00')
                ->credit('1300', '1.00'),
        );
    }

    public function testSaveManyReportsPerTransaction(): void
    {
        $http = ReplayClient::fixture('journal-transaction-save-many-partial');
        $date = new DateTimeImmutable('2026-11-04');

        $result = $this->twinfield($http)->journalTransactions()->saveMany(
            JournalTransactionInput::create('MEMO', $date)
                ->debit('4008', '435.55')
                ->credit('1300', '435.55', customer: '1000', invoice: '11001770'),
            JournalTransactionInput::create('MEMO', $date)
                ->debit('4008', '10.00')
                ->credit('1300', '10.00', customer: '9999'),
        );

        self::assertStringStartsWith(
            '<transactions><transaction destiny="temporary">',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertFalse($result->allSaved());

        $saved = $result->saved()[0];
        self::assertInstanceOf(SavedTransaction::class, $saved);
        self::assertInstanceOf(JournalTransaction::class, $saved->transaction);
        self::assertSame(202600005, $saved->transaction->number());

        $failed = $result->failed()[0];
        self::assertInstanceOf(FailedTransaction::class, $failed);
        self::assertSame('transactions/transaction[2]/lines/line[2]/dim2', $failed->errors[0]->path);
        self::assertSame('Relatie 9999 bestaat niet.', $failed->errors[0]->text);
        self::assertSame('De datum valt buiten de periode.', $failed->warnings[0]->text);
    }

    public function testPostsATransaction(): void
    {
        $http = ReplayClient::fixture('journal-transaction-post');

        $this->twinfield($http)->journalTransactions()->post('MEMO', 202600004);

        self::assertSame(
            '<transaction action="post"><office>1000</office><code>MEMO</code><number>202600004</number></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeletesATransaction(): void
    {
        $http = ReplayClient::fixture('journal-transaction-delete');

        $this->twinfield($http)->journalTransactions()->delete('MEMO', 202600004, 'Booked twice');

        self::assertSame(
            '<transaction action="delete" reason="Booked twice"><office>1000</office><code>MEMO</code>'
            . '<number>202600004</number></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(
            TestTokens::provider('https://example.test'),
            new ReplayClient(),
            $psr17,
            $psr17,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Journal transactions belong to a company');

        $twinfield->journalTransactions()->get('MEMO', 202600003);
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
