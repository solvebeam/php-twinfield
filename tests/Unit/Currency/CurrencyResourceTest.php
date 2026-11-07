<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Currency;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Currency\CurrencyAlreadyExistsException;
use SolveBeam\Twinfield\Currency\CurrencyInput;
use SolveBeam\Twinfield\Currency\CurrencyNotFoundException;
use SolveBeam\Twinfield\Currency\CurrencyStatus;
use SolveBeam\Twinfield\Currency\CurrencySummary;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class CurrencyResourceTest extends TestCase
{
    public function testSearchesCurrencies(): void
    {
        $http = ReplayClient::fixture('finder-currencies');

        $page = $this->twinfield($http)->currencies()->search('U*')->inName()->limit(10)->offset(20)->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new CurrencySummary('EUR', 'Euro'),
            new CurrencySummary('USD', 'US dollar'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertSame('CUR', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('U*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('2', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('21', $xpath->evaluate('string(//tw:Search/tw:firstRow)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame(0.0, $xpath->evaluate('count(//tw:Search/tw:options/tw:ArrayOfString)'), 'The company comes from the header.');
    }

    public function testReadsACurrencyAfterCheckingTheFinder(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-currencies-usd'), ReplayClient::response('currency-read'));

        $usd = $this->twinfield($http)->currencies()->get('USD');

        self::assertCount(2, $http->requests);

        $finder = $this->soapXpath($http->requests[0]);
        self::assertSame('CUR', $finder->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('USD', $finder->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $finder->evaluate('string(//tw:Search/tw:field)'), 'Searches the code only.');

        self::assertSame(
            '<currency><office>1000</office><code>USD</code></currency>',
            $this->xmlRequest($http->requests[1]),
        );

        self::assertSame('1000', $usd->company);
        self::assertSame('USD', $usd->code);
        self::assertSame('US dollar', $usd->name);
        self::assertSame('$', $usd->shortName);
        self::assertSame(CurrencyStatus::Active, $usd->status);
        self::assertCount(2, $usd->rates);
        self::assertEquals(new DateTimeImmutable('2026-01-01'), $usd->rates[0]->startDate);
        self::assertSame('1.085', $usd->rates[0]->rate->value);
        self::assertEquals(new DateTimeImmutable('2026-07-01'), $usd->rates[1]->startDate);
        self::assertSame('1.0912', $usd->rates[1]->rate->value);
        self::assertSame('1.085', $usd->rateOn(new DateTimeImmutable('2026-03-15'))?->rate->value);
    }

    public function testReadsACurrencyInACurrenciesElement(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-currencies-usd'), ReplayClient::response('currency-read-wrapped'));

        self::assertSame('USD', $this->twinfield($http)->currencies()->get('USD')->code);
    }

    public function testReadsACurrencyWithoutRatesAndShortName(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-currencies'), ReplayClient::response('currency-read-no-rates'));

        $eur = $this->twinfield($http)->currencies()->get('EUR');

        self::assertNull($eur->shortName);
        self::assertSame([], $eur->rates);
        self::assertNull($eur->latestRate());
    }

    public function testDoesNotReadACodeThatIsNotInTheFinder(): void
    {
        // USDT matches the search on USD, but is not the same code.
        $http = ReplayClient::fixture('finder-currencies-usdt');

        try {
            $this->twinfield($http)->currencies()->get('USD');
            self::fail('Expected CurrencyNotFoundException.');
        } catch (CurrencyNotFoundException $exception) {
            self::assertSame('USD', $exception->currencyCode);
            self::assertSame('Currency USD not found.', $exception->getMessage());
        }

        self::assertCount(1, $http->requests, 'No <currency> request, it would create the currency.');
    }

    public function testFindReturnsNullForAnUnknownCode(): void
    {
        $http = ReplayClient::fixture('finder-currencies-usdt');

        self::assertNull($this->twinfield($http)->currencies()->find('USD'));
    }

    public function testExists(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-currencies-usd'), ReplayClient::response('finder-currencies-usdt'));
        $currencies = $this->twinfield($http)->currencies();

        self::assertTrue($currencies->exists('USD'));
        self::assertFalse($currencies->exists('USD'));
    }

    public function testThrowsOnAnInvalidRate(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-currencies-usd'), ReplayClient::response('currency-read-invalid-rate'));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('currency/rates/rate/rate is "1,085", expected a positive exchange rate, e.g. 1.0850.');

        $this->twinfield($http)->currencies()->get('USD');
    }

    public function testThrowsOnAnUnknownStatus(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-currencies-usd'), ReplayClient::response('currency-read-unknown-status'));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('currency/@status is "hide", expected one of active, deleted.');

        $this->twinfield($http)->currencies()->get('USD');
    }

    public function testCreatesACurrencyAndReadsItBack(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-currencies'),
            ReplayClient::response('currency-save'),
            ReplayClient::response('currency-read-gbp'),
        );

        $gbp = $this->twinfield($http)->currencies()->create(
            CurrencyInput::create('GBP', 'Pound sterling')
                ->shortName('£')
                ->rate(new DateTimeImmutable('2026-01-01'), '0.8650'),
        );

        self::assertCount(3, $http->requests);
        self::assertSame(
            '<currency><office>1000</office><code>GBP</code><name>Pound sterling</name><shortname>£</shortname>'
            . '<rates><rate><startdate>20260101</startdate><rate>0.865</rate></rate></rates></currency>',
            $this->xmlRequest($http->requests[1]),
        );
        self::assertSame('<currency><office>1000</office><code>GBP</code></currency>', $this->xmlRequest($http->requests[2]));
        self::assertSame('GBP', $gbp->code);
        self::assertSame('0.865', $gbp->latestRate()?->rate->value);
    }

    public function testDoesNotCreateAnExistingCurrency(): void
    {
        $http = ReplayClient::fixture('finder-currencies-usd');

        $this->expectException(CurrencyAlreadyExistsException::class);
        $this->expectExceptionMessage('Currency USD already exists.');

        $this->twinfield($http)->currencies()->create(CurrencyInput::create('USD', 'US dollar'));
    }

    public function testUpdatesACurrencyWithAllItsRates(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-currencies-usd'),
            ReplayClient::response('currency-read'),
            ReplayClient::response('finder-currencies-usd'),
            ReplayClient::response('currency-read'),
            ReplayClient::response('currency-read'),
        );
        $currencies = $this->twinfield($http)->currencies();

        $usd = $currencies->get('USD');
        $currencies->update($usd->edit()->rate(new DateTimeImmutable('2026-10-01'), '1.1')->removeRate(new DateTimeImmutable('2026-07-01')));

        self::assertSame(
            '<currency><office>1000</office><code>USD</code><name>US dollar</name><shortname>$</shortname><rates>'
            . '<rate><startdate>20260101</startdate><rate>1.085</rate></rate>'
            . '<rate><startdate>20261001</startdate><rate>1.1</rate></rate>'
            . '<rate status="deleted"><startdate>20260701</startdate><rate>1.0912</rate></rate>'
            . '</rates></currency>',
            $this->xmlRequest($http->requests[3]),
        );
    }

    public function testDoesNotUpdateAnUnknownCurrency(): void
    {
        $http = ReplayClient::fixture('finder-currencies-usdt');

        $this->expectException(CurrencyNotFoundException::class);

        $this->twinfield($http)->currencies()->update(CurrencyInput::create('USD', 'US dollar'));
    }

    public function testSaveThrowsTwinfieldRefusals(): void
    {
        $http = ReplayClient::fixture('currency-save-error');

        try {
            $this->twinfield($http)->currencies()->save(CurrencyInput::create('GBP', 'Pound sterling'));
            self::fail('Expected ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame('currency/name', $exception->result->errors()[0]->path);
            self::assertSame('Naam is te lang.', $exception->result->errors()[0]->text);
        }

        self::assertCount(1, $http->requests, 'save() does not check the Finder.');
    }

    public function testDeletesACurrency(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-currencies-usd'), ReplayClient::response('currency-delete'));

        $this->twinfield($http)->currencies()->delete('USD');

        self::assertSame(
            '<currency status="deleted"><office>1000</office><code>USD</code></currency>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDoesNotDeleteAnUnknownCurrency(): void
    {
        $http = ReplayClient::fixture('finder-currencies-usdt');

        $this->expectException(CurrencyNotFoundException::class);

        $this->twinfield($http)->currencies()->delete('USD');
    }

    public function testThrowsWhenACurrencyIsStillActiveAfterDeleting(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-currencies-usd'), ReplayClient::response('currency-delete-still-active'));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Currency USD is still active after deleting it.');

        $this->twinfield($http)->currencies()->delete('USD');
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $http = new ReplayClient();
        $twinfield = Twinfield::create(new StaticAccessTokenProvider('token-123', 'https://example.test'), $http, $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Currencies belong to a company: use company() first.');

        $twinfield->currencies()->save(CurrencyInput::create('USD', 'US dollar'));
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(new StaticAccessTokenProvider('token-123', 'https://example.test'), $http, $psr17, $psr17)->company('1000');
    }

    private function soapXpath(RequestInterface $request): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML((string) $request->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        return $xpath;
    }

    private function xmlRequest(RequestInterface $request): string
    {
        $xmlRequest = $this->soapXpath($request)->evaluate('string(//tw:ProcessXmlString/tw:xmlRequest)');

        self::assertIsString($xmlRequest);

        return $xmlRequest;
    }
}
