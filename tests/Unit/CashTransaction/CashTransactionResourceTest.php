<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\CashTransaction;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\CashTransaction\CashTransaction;
use SolveBeam\Twinfield\CashTransaction\CashTransactionInput;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;
use SolveBeam\Twinfield\Twinfield;

final class CashTransactionResourceTest extends TestCase
{
    public function testReadsATransaction(): void
    {
        $http = ReplayClient::fixture('cash-transaction-read');

        $transaction = $this->twinfield($http)->cashTransactions()->get('KAS', 202600046);

        self::assertSame(
            '<read><type>transaction</type><office>1000</office><code>KAS</code><number>202600046</number></read>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertInstanceOf(CashTransaction::class, $transaction);
        self::assertSame('KAS', $transaction->dayBook());
        self::assertSame('1000', $transaction->total->dim1->code);
        self::assertSame('121.00', $transaction->balance()->value);
        self::assertInstanceOf(CashTransactionInput::class, $transaction->edit());
        self::assertSame('KAS', $transaction->edit()->dayBook());
    }

    public function testCreatesADayOfTheCashRegister(): void
    {
        $http = ReplayClient::fixture('cash-transaction-read');

        $this->twinfield($http)->cashTransactions()->create(
            CashTransactionInput::create('KAS', '1000', new DateTimeImmutable('2026-11-03'))
                ->statement(311, startValue: '250.00')
                ->receive('8000', '413.22', vatCode: 'VH', vatValue: '86.78', description: 'Sales 21% VAT')
                ->receive('8010', '91.74', vatCode: 'VL', vatValue: '8.26', description: 'Sales 9% VAT')
                ->pay('4300', '20.00', vatCode: 'IH', vatValue: '4.20', description: 'Coffee and milk'),
        );

        $document = new DOMDocument();
        $document->loadXML($this->xmlRequest($http->lastRequest()));
        $xpath = new DOMXPath($document);

        // 500.00 + 100.00 in, 24.20 out.
        self::assertSame('debit', $xpath->evaluate('string(//line[@type="total"]/debitcredit)'));
        self::assertSame('575.80', $xpath->evaluate('string(//line[@type="total"]/value)'));
        self::assertSame('825.80', $xpath->evaluate('string(//header/closevalue)'));
        self::assertSame('KAS', $xpath->evaluate('string(//header/code)'));
    }

    public function testReadThrowsNotFound(): void
    {
        $this->expectException(TransactionNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('bank-transaction-read-not-found'))->cashTransactions()->get('KAS', 1);
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
