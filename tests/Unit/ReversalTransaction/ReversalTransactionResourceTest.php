<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ReversalTransaction;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class ReversalTransactionResourceTest extends TestCase
{
    public function testReadsAReversalWithoutReversedTransaction(): void
    {
        $http = ReplayClient::fixture('reversal-transaction-get');

        $reversal = $this->twinfield($http)->reversalTransactions()->get('MEMO', 202600003);

        self::assertSame(
            '<reverse action="getreversetransactioninfo"><original><office>1000</office><code>MEMO</code>'
            . '<number>202600003</number></original></reverse>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame('MEMO', $reversal->original->dayBook);
        self::assertSame(202600003, $reversal->original->number);
        self::assertTrue($reversal->original->company->equals(TestTokens::company('1000')));
        self::assertNull($reversal->reversed);
        self::assertFalse($reversal->isReversed());
        self::assertFalse($reversal->reverseInNextPeriod);
    }

    public function testReadsAReversalWithReversedTransaction(): void
    {
        $http = ReplayClient::fixture('reversal-transaction-get-reversed');

        $reversal = $this->twinfield($http)->reversalTransactions()->get('MEMO', 202600003);

        self::assertNotNull($reversal->reversed);
        self::assertTrue($reversal->isReversed());
        self::assertSame('MEMOTEGENBOEKING', $reversal->reversed->dayBook);
        self::assertSame(202600001, $reversal->reversed->number);
        self::assertSame('1000', $reversal->reversed->company->code);
        self::assertTrue($reversal->reverseInNextPeriod);
    }

    public function testThrowsWhenTheTransactionIsNotFound(): void
    {
        $http = ReplayClient::fixture('reversal-transaction-get-not-found');

        $this->expectException(ProcessXmlException::class);
        $this->expectExceptionMessage('Originele transactie MEMO:202699999 is niet gevonden.');

        $this->twinfield($http)->reversalTransactions()->get('MEMO', 202699999);
    }

    public function testPostsFinal(): void
    {
        $http = ReplayClient::fixture('reversal-transaction-post-final');

        $reversed = $this->twinfield($http)->reversalTransactions()->postFinal('MEMO', 202600003);

        self::assertSame(
            '<reverse action="postfinal"><original><office>1000</office><code>MEMO</code>'
            . '<number>202600003</number></original></reverse>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame('MEMOTEGENBOEKING', $reversed->dayBook);
        self::assertSame(202600001, $reversed->number);
    }

    public function testPostFinalNeedsTheReversedTransaction(): void
    {
        $http = ReplayClient::fixture('reversal-transaction-get');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('reverse/reversed is missing after postfinal.');

        $this->twinfield($http)->reversalTransactions()->postFinal('MEMO', 202600003);
    }

    public function testPostsProvisional(): void
    {
        $http = ReplayClient::fixture('reversal-transaction-post-provisional');

        $reversal = $this->twinfield($http)->reversalTransactions()->postProvisional('MEMO', 202600003);

        self::assertSame(
            '<reverse action="postprovisional"><original><office>1000</office><code>MEMO</code>'
            . '<number>202600003</number></original></reverse>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertNull($reversal->reversed);
        self::assertTrue($reversal->reverseInNextPeriod);
    }

    public function testUndoes(): void
    {
        $http = ReplayClient::fixture('reversal-transaction-undo');

        $this->twinfield($http)->reversalTransactions()->undo('MEMO', 202600003);

        self::assertSame(
            '<reverse action="undo"><original><office>1000</office><code>MEMO</code>'
            . '<number>202600003</number></original></reverse>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testThrowsWhenTheResponseIsAboutAnotherTransaction(): void
    {
        $http = ReplayClient::fixture('reversal-transaction-get');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Asked for the reversal of MEMO 202600004 in 1000, got MEMO 202600003 in 1000.');

        $this->twinfield($http)->reversalTransactions()->get('MEMO', 202600004);
    }

    public function testThrowsOnAnotherRootElement(): void
    {
        $http = ReplayClient::fixture('journal-transaction-post');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected a <reverse> response, got <transaction>.');

        $this->twinfield($http)->reversalTransactions()->get('MEMO', 202600004);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function invalidTransactions(): iterable
    {
        yield 'empty day book' => ['', 202600003];
        yield 'lowercase day book' => ['memo', 202600003];
        yield 'number zero' => ['MEMO', 0];
    }

    #[DataProvider('invalidTransactions')]
    public function testRefusesAnInvalidTransactionBeforeSending(string $dayBook, int $number): void
    {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->reversalTransactions()->get($dayBook, $number);
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertSame([], $http->requests);
        }
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
        $this->expectExceptionMessage('The client has no company');

        $twinfield->reversalTransactions()->get('MEMO', 202600003);
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
