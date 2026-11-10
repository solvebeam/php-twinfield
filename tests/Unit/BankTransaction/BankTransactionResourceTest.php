<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\BankTransaction;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\BankTransaction\BankTransaction;
use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\DetailLineInput;
use SolveBeam\Twinfield\Transaction\FailedTransaction;
use SolveBeam\Twinfield\Transaction\MatchStatus;
use SolveBeam\Twinfield\Transaction\SavedTransaction;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;
use SolveBeam\Twinfield\Transaction\TransactionStatus;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class BankTransactionResourceTest extends TestCase
{
    public function testReadsATransaction(): void
    {
        $http = ReplayClient::fixture('bank-transaction-read');

        $transaction = $this->twinfield($http)->bankTransactions()->get('BNK', 202600045);

        $request = $this->xmlRequest($http->lastRequest());
        self::assertSame(
            '<read><type>transaction</type><office>1000</office><code>BNK</code><number>202600045</number></read>',
            $request,
        );

        $header = $transaction->header;
        self::assertEquals(TestTokens::company('1000'), $header->company);
        self::assertSame('BNK', $header->dayBook);
        self::assertSame(202600045, $transaction->number());
        self::assertSame(TransactionStatus::Provisional, $header->status);
        self::assertTrue($header->isProvisional());
        self::assertSame('2026/11', (string) $header->period);
        self::assertSame('EUR', $header->currency);
        self::assertEquals(new DateTimeImmutable('2026-11-03 00:00:00'), $header->date);
        self::assertSame('import', $header->origin);
        self::assertSame('EXAMPLE', $header->user);
        self::assertEquals(new DateTimeImmutable('2026-11-03 10:15:30'), $header->inputDate);
        self::assertEquals(new DateTimeImmutable('2026-11-04 09:12:00'), $header->modificationDate);
        self::assertSame('Statement 245', $header->freeText1);
        self::assertNull($header->freeText2);

        self::assertSame(245, $transaction->statementNumber);
        self::assertSame('974.01', $transaction->startValue->value);
        self::assertSame('1397.46', $transaction->closeValue->value);
        self::assertSame('423.45', $transaction->balance()->value);

        $total = $transaction->total;
        self::assertSame(1, $total->id);
        self::assertSame('1010', $total->dim1->code);
        self::assertSame('Bank', $total->dim1->name);
        self::assertSame(DebitCredit::Debit, $total->debitCredit);
        self::assertSame('423.45', $total->value->value);
        self::assertSame('1', $total->rate);
        self::assertSame('635.18', $total->repValue?->value);
        self::assertSame('1.500000000', $total->repRate);
        self::assertSame(MatchStatus::NotMatchable, $total->matchStatus);

        self::assertCount(2, $transaction->details);
        [$payment, $costs] = $transaction->details;

        self::assertSame(2, $payment->id);
        self::assertSame('1300', $payment->dim1->code);
        self::assertSame('1000', $payment->dim2?->code);
        self::assertSame('Example customer', $payment->dim2->name);
        self::assertTrue($payment->dim2->isCustomer());
        self::assertSame(DebitCredit::Credit, $payment->debitCredit);
        self::assertSame('435.55', $payment->signedValue()->value);
        self::assertSame('2026-0042', $payment->invoiceNumber);
        self::assertEquals(new DateTimeImmutable('2026-11-08 00:00:00'), $payment->dueDate);
        self::assertNull($payment->lineDate, 'An empty <currencydate/> is no line date.');
        self::assertSame(MatchStatus::Matched, $payment->matchStatus);
        self::assertSame(2, $payment->matchLevel);
        self::assertSame('0.00', $payment->openValue?->value);
        self::assertCount(1, $payment->matches);
        self::assertSame(TransactionStatus::Final, $payment->matches[0]->status);
        self::assertSame('435.55', $payment->matches[0]->matchValue->value);
        self::assertSame('VRK', $payment->matches[0]->lines[0]->dayBook);
        self::assertSame(202600012, $payment->matches[0]->lines[0]->number);
        self::assertSame(1, $payment->matches[0]->lines[0]->line);

        self::assertSame('4500', $costs->dim1->code);
        self::assertSame('KPL01', $costs->dim2?->code);
        self::assertSame(DebitCredit::Debit, $costs->debitCredit);
        self::assertSame('IH', $costs->vatCode);
        self::assertSame('2.10', $costs->vatValue?->value);
        self::assertSame('Monthly fee', $costs->comment);
        self::assertSame([], $costs->matches);

        self::assertCount(1, $transaction->vat);
        $vat = $transaction->vat[0];
        self::assertSame('1520', $vat->dim1->code);
        self::assertSame('IH', $vat->vatCode);
        self::assertSame('2.10', $vat->value->value);
        self::assertSame('10.00', $vat->vatTurnover?->value);
        self::assertSame(3, $vat->baseline);
    }

    public function testEditKeepsAllValues(): void
    {
        $transaction = $this->twinfield(ReplayClient::fixture('bank-transaction-read'))->bankTransactions()->get('BNK', 202600045);

        $input = $transaction->edit();

        self::assertSame(202600045, $input->transactionNumber());
        self::assertSame($transaction->balance()->value, $input->balance()->value);
        self::assertSame($transaction->closeValue->value, $input->closeValue()?->value);

        $document = new DOMDocument();
        $document->loadXML($input->toXml('1000')->toXml());
        $xpath = new DOMXPath($document);

        self::assertSame('202600045', $xpath->evaluate('string(//header/number)'));
        self::assertSame('2026/11', $xpath->evaluate('string(//header/period)'));
        self::assertSame('245', $xpath->evaluate('string(//header/statementnumber)'));
        self::assertSame('974.01', $xpath->evaluate('string(//header/startvalue)'));
        self::assertSame('1397.46', $xpath->evaluate('string(//header/closevalue)'));
        self::assertSame('Statement 245', $xpath->evaluate('string(//header/freetext1)'));
        self::assertSame('Statement 245', $xpath->evaluate('string(//line[@type="total"]/description)'));
        self::assertSame('1000', $xpath->evaluate('string(//line[@id="2"]/dim2)'));
        self::assertSame('2026-0042', $xpath->evaluate('string(//line[@id="2"]/invoicenumber)'));
        self::assertSame('20261108', $xpath->evaluate('string(//line[@id="2"]/duedate)'));
        self::assertSame('IH', $xpath->evaluate('string(//line[@id="3"]/vatcode)'));
        self::assertSame('vat', $xpath->evaluate('string(//line[@id="4"]/@type)'));
        self::assertSame(0.0, $xpath->evaluate('count(//basevalue)'), 'Twinfield calculates base amounts again.');
        self::assertCount(1, $transaction->details[0]->matches);
        self::assertSame(0.0, $xpath->evaluate('count(//matches)'), 'Matched lines are not matched again.');
    }

    public function testReadThrowsNotFound(): void
    {
        $bank = $this->twinfield(ReplayClient::fixture('bank-transaction-read-not-found'))->bankTransactions();

        try {
            $bank->get('BNK', 202699999);
            self::fail('Expected TransactionNotFoundException.');
        } catch (TransactionNotFoundException $exception) {
            self::assertSame('BNK', $exception->dayBook);
            self::assertSame(202699999, $exception->number);
            self::assertSame('De boeking bestaat niet.', $exception->reason);
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $bank = $this->twinfield(ReplayClient::fixture('bank-transaction-read-not-found'))->bankTransactions();

        self::assertNull($bank->find('BNK', 202699999));
    }

    public function testCreatesATransaction(): void
    {
        $http = ReplayClient::fixture('bank-transaction-save');

        $transaction = $this->twinfield($http)->bankTransactions()->create(
            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
                ->statement(246, '1397.46')
                ->receive('1300', '121.00', customer: '1000', invoice: '2026-0043', description: 'Invoice 2026-0043'),
        );

        self::assertStringStartsWith('<transaction destiny="temporary"><header><office>1000</office>', $this->xmlRequest($http->lastRequest()));
        self::assertSame(202600046, $transaction->number());
        self::assertSame('1518.46', $transaction->closeValue->value);
        self::assertSame(MatchStatus::Available, $transaction->details[0]->matchStatus);
        self::assertNull($transaction->total->repValue, 'Without reporting currency, Twinfield leaves it out.');
    }

    public function testCreateRefusesATransactionNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('use update()');

        $this->twinfield(new ReplayClient())->bankTransactions()->create(
            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
                ->number(202600045)
                ->receive('1300', '1.00'),
        );
    }

    public function testUpdateReadsFirst(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('bank-transaction-read'),
            ReplayClient::response('bank-transaction-read'),
            ReplayClient::response('bank-transaction-read'),
        );
        $bank = $this->twinfield($http)->bankTransactions();

        $transaction = $bank->get('BNK', 202600045);
        $bank->update($transaction->edit()->description('Statement 245 (checked)'));

        // get(), then update(): a read to check it exists, and the save.
        self::assertCount(3, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[1]));
        self::assertStringStartsWith('<transaction destiny="temporary">', $this->xmlRequest($http->requests[2]));
        self::assertStringContainsString(
            '<description>Statement 245 (checked)</description>',
            $this->xmlRequest($http->requests[2]),
        );
    }

    public function testUpdateThrowsNotFound(): void
    {
        $http = ReplayClient::fixture('bank-transaction-read-not-found');

        $this->expectException(TransactionNotFoundException::class);

        $this->twinfield($http)->bankTransactions()->update(
            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
                ->number(202699999)
                ->receive('1300', '1.00'),
        );
    }

    public function testUpdateNeedsANumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Updating needs the transaction number');

        $this->twinfield(new ReplayClient())->bankTransactions()->update(
            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))->receive('1300', '1.00'),
        );
    }

    public function testSaveManyReportsPerTransaction(): void
    {
        $http = ReplayClient::fixture('bank-transaction-save-many-partial');
        $base = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'));

        $result = $this->twinfield($http)->bankTransactions()->saveMany(
            $base->receive('1300', '50.00', customer: '1000'),
            $base->receive('1300', '75.00', customer: '9999'),
        );

        $request = $this->xmlRequest($http->lastRequest());
        self::assertStringStartsWith('<transactions><transaction destiny="temporary"><header>', $request);
        self::assertSame(2, substr_count($request, '<transaction '));

        self::assertCount(2, $result);
        self::assertFalse($result->allSaved());
        self::assertCount(1, $result->saved());
        self::assertCount(1, $result->failed());

        $saved = $result->saved()[0];
        self::assertInstanceOf(SavedTransaction::class, $saved);
        self::assertSame(0, $saved->index);
        self::assertInstanceOf(BankTransaction::class, $saved->transaction);
        self::assertSame(202600047, $saved->transaction->number());
        self::assertSame([], $saved->warnings);

        $failed = $result->failed()[0];
        self::assertInstanceOf(FailedTransaction::class, $failed);
        self::assertSame(1, $failed->index);
        self::assertCount(1, $failed->errors);
        self::assertSame('transactions/transaction[2]/lines/line[2]/dim2', $failed->errors[0]->path);
        self::assertSame('Relatie 9999 bestaat niet.', $failed->errors[0]->text);
        self::assertCount(1, $failed->warnings);
        self::assertSame('transactions/transaction[2]/lines/line[2]/dim2: Relatie 9999 bestaat niet.', $failed->message());
    }

    public function testSaveManyWithoutInputsSendsNothing(): void
    {
        $http = new ReplayClient();

        $result = $this->twinfield($http)->bankTransactions()->saveMany();

        self::assertCount(0, $result);
        self::assertSame([], $http->requests);
    }

    public function testSaveManyChecksTheNumberOfResults(): void
    {
        $http = ReplayClient::fixture('bank-transaction-save-many-partial');
        $input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))->receive('1300', '1.00');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Sent 3 transactions, got 2 back.');

        $this->twinfield($http)->bankTransactions()->saveMany($input, $input, $input);
    }

    public function testPostsATransaction(): void
    {
        $http = ReplayClient::fixture('bank-transaction-post');

        $this->twinfield($http)->bankTransactions()->post('BNK', 202600046);

        self::assertSame(
            '<transaction action="post"><office>1000</office><code>BNK</code><number>202600046</number></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeletesATransaction(): void
    {
        $http = ReplayClient::fixture('bank-transaction-delete');

        $this->twinfield($http)->bankTransactions()->delete('BNK', 202600046, 'Imported twice');

        self::assertSame(
            '<transaction action="delete" reason="Imported twice"><office>1000</office><code>BNK</code>'
            . '<number>202600046</number></transaction>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteOfAFinalTransactionThrows(): void
    {
        $http = ReplayClient::fixture('bank-transaction-delete-final');

        $this->expectException(ProcessXmlException::class);
        $this->expectExceptionMessage('transaction: Alleen voorlopige boekingen kunnen worden verwijderd.');

        $this->twinfield($http)->bankTransactions()->delete('BNK', 202600045, 'Imported twice');
    }

    public function testDeleteNeedsAReason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield(new ReplayClient())->bankTransactions()->delete('BNK', 202600045, ' ');
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Bank transactions belong to a company');

        $twinfield->bankTransactions()->get('BNK', 1);
    }

    public function testRefusesAnUnknownLineType(): void
    {
        $http = new ReplayClient($this->response(str_replace(
            'type="vat" id="4"',
            'type="quantity" id="4"',
            $this->fixtureXml('bank-transaction-read'),
        )));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('transaction/lines/line[4]/@type is "quantity", expected one of total, detail, vat.');

        $this->twinfield($http)->bankTransactions()->get('BNK', 202600045);
    }

    public function testRefusesAMissingTotalLine(): void
    {
        $xml = (string) preg_replace('~<line type="total".*?</line>~', '', $this->fixtureXml('bank-transaction-save'));
        $http = new ReplayClient($this->response($xml));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('transaction/lines has no total line.');

        $this->twinfield($http)->bankTransactions()->get('BNK', 202600046);
    }

    public function testRefusesAnInvalidAmount(): void
    {
        $http = new ReplayClient($this->response(str_replace(
            '<value>121.00</value><invoicenumber>',
            '<value>121,00</value><invoicenumber>',
            $this->fixtureXml('bank-transaction-save'),
        )));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('transaction/lines/line[2]/value is "121,00", expected an amount.');

        $this->twinfield($http)->bankTransactions()->get('BNK', 202600046);
    }

    public function testRefusesAMissingDebitCredit(): void
    {
        $http = new ReplayClient($this->response(str_replace(
            '<debitcredit>credit</debitcredit>',
            '',
            $this->fixtureXml('bank-transaction-save'),
        )));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('transaction/lines/line[2]/debitcredit is missing.');

        $this->twinfield($http)->bankTransactions()->get('BNK', 202600046);
    }

    public function testLinesCanBeBuiltWithFullControl(): void
    {
        $http = ReplayClient::fixture('bank-transaction-save');

        $this->twinfield($http)->bankTransactions()->save(
            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
                ->line(DetailLineInput::credit('1300', '121.00')->customer('1000')),
        );

        self::assertStringContainsString(
            '<line type="detail" id="2"><dim1>1300</dim1><dim2>1000</dim2><debitcredit>credit</debitcredit>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    /**
     * The ProcessXml response of a fixture, unescaped.
     */
    private function fixtureXml(string $name): string
    {
        $document = new DOMDocument();
        $document->load(dirname(__DIR__, 2) . '/Fixtures/soap/' . $name . '.xml');

        return (string) $document->getElementsByTagName('ProcessXmlStringResult')->item(0)?->textContent;
    }

    private function response(string $xml): Response
    {
        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            . '<ProcessXmlStringResponse xmlns="http://www.twinfield.com/"><ProcessXmlStringResult>'
            . htmlspecialchars($xml, ENT_XML1)
            . '</ProcessXmlStringResult></ProcessXmlStringResponse></soap:Body></soap:Envelope>';

        return new Response(200, ['Content-Type' => 'text/xml; charset=utf-8'], $body);
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
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
