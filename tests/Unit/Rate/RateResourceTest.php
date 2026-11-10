<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Rate;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Rate\RateAlreadyExistsException;
use SolveBeam\Twinfield\Rate\RateInput;
use SolveBeam\Twinfield\Rate\RateNotFoundException;
use SolveBeam\Twinfield\Rate\RateStatus;
use SolveBeam\Twinfield\Rate\RateSummary;
use SolveBeam\Twinfield\Rate\RateType;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class RateResourceTest extends TestCase
{
    public function testSearchesRates(): void
    {
        $http = ReplayClient::fixture('finder-rates');

        $page = $this->twinfield($http)
            ->rates()
            ->search('K*')
            ->inCode()
            ->rateType(RateType::Quantity)
            ->limit(10)
            ->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new RateSummary(TestTokens::company(), 'CONSULT', 'Consultancy'),
            new RateSummary(TestTokens::company(), 'KM', 'Kilometres'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertSame('TRT', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('K*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame('ratetype', $xpath->evaluate('string(//tw:Search/tw:options/tw:ArrayOfString/tw:string[1])'));
        self::assertSame('quantity', $xpath->evaluate('string(//tw:Search/tw:options/tw:ArrayOfString/tw:string[2])'));
    }

    public function testSearchSendsNoOptionsByDefault(): void
    {
        $http = ReplayClient::fixture('finder-rates');

        $this->twinfield($http)->rates()->search()->get();

        self::assertSame(
            0.0,
            $this->soapXpath($http->lastRequest())->evaluate('count(//tw:Search/tw:options/tw:ArrayOfString)'),
            'The company comes from the header.',
        );
    }

    public function testReadsATimeRate(): void
    {
        $http = ReplayClient::fixture('rate-read');

        $rate = $this->twinfield($http)->rates()->get('CONSULT');

        self::assertSame(
            '<read><type>projectrate</type><office>1000</office><code>CONSULT</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertEquals(TestTokens::company('1000'), $rate->company);
        self::assertSame('CONSULT', $rate->code);
        self::assertSame('Consultancy', $rate->name);
        self::assertSame('Consult', $rate->shortName);
        self::assertSame(RateStatus::Active, $rate->status);
        self::assertTrue($rate->inUse);
        self::assertSame(RateType::Time, $rate->type);
        self::assertSame(1, $rate->unit);
        self::assertSame('EUR', $rate->currency);
        self::assertEquals(new DateTimeImmutable('2025-01-28 18:54:55'), $rate->created);
        self::assertEquals(new DateTimeImmutable('2026-03-05 10:15:30'), $rate->modified);
        self::assertSame(2, $rate->touched);
        self::assertSame('SUPER', $rate->modifiedBy);

        self::assertCount(1, $rate->prices);
        $price = $rate->prices[0];
        self::assertSame(2, $price->id);
        self::assertSame(RateStatus::Active, $price->status);
        self::assertEquals(new DateTimeImmutable('2025-01-01'), $price->from);
        self::assertNull($price->until, '29991231 means no end date.');
        self::assertSame('55.0000', $price->internal->value);
        self::assertSame('95.0000', $price->external->value);
    }

    public function testReadsAQuantityRateWithPricesSortedByDate(): void
    {
        $rate = $this->twinfield(ReplayClient::fixture('rate-read-quantity'))->rates()->get('KM');

        self::assertSame(RateType::Quantity, $rate->type);
        self::assertFalse($rate->inUse);
        self::assertNull($rate->shortName);
        self::assertSame([2, 3], array_map(static fn ($price): int => $price->id, $rate->prices));
        self::assertEquals(new DateTimeImmutable('2025-12-31'), $rate->prices[0]->until);

        self::assertSame('0.1500', (string) $rate->priceOn(new DateTimeImmutable('2025-12-31 23:59'))?->internal);
        self::assertSame('0.1600', (string) $rate->priceOn(new DateTimeImmutable('2026-01-01'))?->internal);
        self::assertSame('0.1600', (string) $rate->priceOn(new DateTimeImmutable('2999-12-31'))?->internal);
        self::assertNull($rate->priceOn(new DateTimeImmutable('2024-12-31')));
        self::assertSame(3, $rate->latestPrice()->id);
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('rate-read-not-found'))->rates()->get('T999');
            self::fail('Expected a RateNotFoundException.');
        } catch (RateNotFoundException $exception) {
            self::assertSame('T999', $exception->rateCode);
            self::assertSame('Tarief T999 bestaat niet.', $exception->reason);
            self::assertSame('Rate T999 not found: Tarief T999 bestaat niet.', $exception->getMessage());
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testFind(): void
    {
        self::assertNull($this->twinfield(ReplayClient::fixture('rate-read-not-found'))->rates()->find('T999'));
        self::assertSame(
            'CONSULT',
            $this->twinfield(ReplayClient::fixture('rate-read'))->rates()->find('CONSULT')?->code,
        );
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unexpectedResponses(): iterable
    {
        yield 'unknown type' => ['<type>time</type>', '<type>expense</type>', 'projectrate/type is "expense"'];
        yield 'unknown status' => [
            'status="active" inuse',
            'status="archived" inuse',
            'projectrate/@status is "archived"',
        ];
        yield 'missing inuse' => ['inuse="true" ', '', 'projectrate/@inuse is missing'];
        yield 'unit 0' => ['<unit>1</unit>', '<unit>0</unit>', 'unit of 1 or more'];
        yield 'empty currency' => ['>EUR</currency>', '></currency>', 'projectrate/currency is empty'];
        yield 'price with comma' => [
            '<externalrate>95.0000',
            '<externalrate>95,0000',
            'ratechange/externalrate is "95,0000"',
        ];
        yield 'negative price' => [
            '<internalrate>55.0000',
            '<internalrate>-5.0000',
            'ratechange/internalrate is "-5.0000"',
        ];
        yield 'invalid date' => [
            '<begindate>20250101',
            '<begindate>2025-01-01',
            'ratechange/begindate is "2025-01-01"',
        ];
        yield 'ends before start' => ['<enddate>29991231', '<enddate>20241231', 'ends on 2024-12-31, before it starts'];
        yield 'line without id' => [' id="2"', '', 'ratechange/@id is missing'];
        yield 'no prices' => [
            '<ratechange lastused="" status="active" id="2"><begindate>20250101</begindate><enddate>29991231</enddate>'
            . '<internalrate>55.0000</internalrate><externalrate>95.0000</externalrate></ratechange>',
            '',
            'projectrate/ratechanges has no <ratechange>',
        ];
    }

    #[DataProvider('unexpectedResponses')]
    public function testThrowsOnUnexpectedResponses(string $search, string $replace, string $message): void
    {
        $fixture = (string) ReplayClient::response('rate-read')->getBody();
        $xml = str_replace(htmlspecialchars($search, ENT_NOQUOTES), htmlspecialchars($replace, ENT_NOQUOTES), $fixture);

        self::assertNotSame($fixture, $xml, 'The fixture must contain the text to replace.');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($message);

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->rates()->get('CONSULT');
    }

    public function testThrowsOnOverlappingPrices(): void
    {
        $fixture = (string) ReplayClient::response('rate-read-quantity')->getBody();
        $xml = str_replace('20251231', '20260101', $fixture);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Rate KM has overlapping prices from 2025-01-01 and 2026-01-01.');

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->rates()->get('KM');
    }

    public function testSavesAndReadsBack(): void
    {
        $http = new ReplayClient(ReplayClient::response('rate-save'), ReplayClient::response('rate-read'));

        $rate = $this->twinfield($http)->rates()->save(
            RateInput::time('CONSULT', 'Consultancy')->price(new DateTimeImmutable('2025-01-01'), '55', '95'),
        );

        self::assertTrue($rate->inUse, 'The rate is read back after saving.');
        self::assertCount(2, $http->requests);
        self::assertSame(
            '<projectrate><office>1000</office><code>CONSULT</code><name>Consultancy</name><shortname/>'
            . '<type>time</type><unit>1</unit><ratechanges><ratechange><begindate>20250101</begindate>'
            . '<enddate>29991231</enddate><internalrate>55.0000</internalrate><externalrate>95.0000</externalrate>'
            . '</ratechange></ratechanges></projectrate>',
            $this->xmlRequest($http->requests[0]),
        );
        self::assertSame(
            '<read><type>projectrate</type><office>1000</office><code>CONSULT</code></read>',
            $this->xmlRequest($http->requests[1]),
        );
    }

    public function testThrowsTheTwinfieldErrorsWhenSavingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('rate-save-error'))->rates()->save(
                RateInput::time('CONSULT', 'Consultancy')
                    ->currency('XXX')
                    ->price(new DateTimeImmutable('2025-01-01'), '55', '95'),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            $errors = $exception->result->errors();

            self::assertCount(1, $errors);
            self::assertSame('projectrate/currency', $errors[0]->path);
            self::assertSame('Valuta XXX bestaat niet.', $errors[0]->text);
        }
    }

    public function testCreatesAfterCheckingTheCodeIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('rate-read-not-found'),
            ReplayClient::response('rate-save'),
            ReplayClient::response('rate-read'),
        );

        $rate = $this->twinfield($http)->rates()->create(
            RateInput::time('CONSULT', 'Consultancy')->price(new DateTimeImmutable('2025-01-01'), '55', '95'),
        );

        self::assertSame('CONSULT', $rate->code);
        self::assertCount(3, $http->requests);
        self::assertStringContainsString('<name>Consultancy</name>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('rate-read');

        try {
            $this->twinfield($http)->rates()->create(
                RateInput::time('CONSULT', 'Other')->price(new DateTimeImmutable('2026-01-01'), '1', '1'),
            );
            self::fail('Expected a RateAlreadyExistsException.');
        } catch (RateAlreadyExistsException $exception) {
            self::assertSame('Rate CONSULT already exists.', $exception->getMessage());
            self::assertSame('CONSULT', $exception->rateCode);
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testUpdatesWithANewPrice(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('rate-read'),
            ReplayClient::response('rate-read'),
            ReplayClient::response('rate-save'),
            ReplayClient::response('rate-read'),
        );
        $rates = $this->twinfield($http)->rates();

        $rates->update($rates->get('CONSULT')->edit()->newPriceFrom(new DateTimeImmutable('2026-07-01'), '60', '105'));

        self::assertCount(4, $http->requests);
        self::assertSame(
            '<projectrate><office>1000</office><code>CONSULT</code>'
            . '<name>Consultancy</name><shortname>Consult</shortname>'
            . '<type>time</type><unit>1</unit><currency>EUR</currency><ratechanges>'
            . '<ratechange id="2"><begindate>20250101</begindate><enddate>20260630</enddate>'
            . '<internalrate>55.0000</internalrate><externalrate>95.0000</externalrate></ratechange>'
            . '<ratechange><begindate>20260701</begindate><enddate>29991231</enddate>'
            . '<internalrate>60.0000</internalrate><externalrate>105.0000</externalrate></ratechange>'
            . '</ratechanges></projectrate>',
            $this->xmlRequest($http->requests[2]),
        );
    }

    public function testUpdateRefusesAMissingRate(): void
    {
        $http = ReplayClient::fixture('rate-read-not-found');

        $this->expectException(RateNotFoundException::class);

        try {
            $this->twinfield($http)->rates()->update(
                RateInput::time('T999', 'Missing')->price(new DateTimeImmutable('2026-01-01'), '1', '1'),
            );
        } finally {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testEditKeepsAllValuesAndLineIds(): void
    {
        $rates = $this->twinfield(ReplayClient::fixture('rate-read-quantity'))->rates();

        self::assertSame(
            '<projectrate><office>1000</office><code>KM</code><name>Kilometres</name><shortname/>'
            . '<type>quantity</type><unit>1</unit><currency>EUR</currency><ratechanges>'
            . '<ratechange id="2"><begindate>20250101</begindate><enddate>20251231</enddate>'
            . '<internalrate>0.1500</internalrate><externalrate>0.2300</externalrate></ratechange>'
            . '<ratechange id="3"><begindate>20260101</begindate><enddate>29991231</enddate>'
            . '<internalrate>0.1600</internalrate><externalrate>0.2300</externalrate></ratechange>'
            . '</ratechanges></projectrate>',
            $rates->get('KM')->edit()->toXml('1000')->toXml(),
        );
    }

    public function testDeletes(): void
    {
        $http = ReplayClient::fixture('rate-delete');

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->rates()->delete('CONSULT'));
        self::assertSame(
            '<projectrate status="deleted"><office>1000</office><code>CONSULT</code></projectrate>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesARateInUse(): void
    {
        self::assertSame(
            DeleteResult::Hidden,
            $this->twinfield(ReplayClient::fixture('rate-delete-hidden'))->rates()->delete('CONSULT'),
        );
    }

    public function testDeleteThrowsNotFound(): void
    {
        $this->expectException(RateNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('rate-read-not-found'))->rates()->delete('T999');
    }

    public function testDeleteThrowsWhenTheRateStaysActive(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Rate CONSULT is still active after deleting it.');

        $this->twinfield(ReplayClient::fixture('rate-activate'))->rates()->delete('CONSULT');
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('rate-activate');

        $this->twinfield($http)->rates()->activate('CONSULT');

        self::assertSame(
            '<projectrate status="active"><office>1000</office><code>CONSULT</code></projectrate>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testRequiresACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(
            TestTokens::provider('https://example.test'),
            new ReplayClient(),
            $psr17,
            $psr17,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Rates belong to a company: use company() first.');

        $twinfield->rates()->get('CONSULT');
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(
            TestTokens::provider('https://example.test'),
            $http,
            $psr17,
            $psr17,
        )->company('1000');
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
