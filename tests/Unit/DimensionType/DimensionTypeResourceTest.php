<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\DimensionType;

use DOMDocument;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\DimensionType\AddressLabels;
use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\DimensionType\DimensionTypeLevels;
use SolveBeam\Twinfield\DimensionType\DimensionTypeNotFoundException;
use SolveBeam\Twinfield\DimensionType\DimensionTypeSummary;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;

final class DimensionTypeResourceTest extends TestCase
{
    public function testSearchesDimensionTypes(): void
    {
        $http = ReplayClient::fixture('finder-dimension-types');

        $page = $this->twinfield($http)->dimensionTypes()->search('*')->inName()->limit(10)->offset(20)->get();

        self::assertSame(8, $page->totalRows);
        self::assertEquals(new DimensionTypeSummary(TestTokens::company(), 'ACT', 'Activiteiten'), $page->items[0]);
        self::assertEquals(new DimensionTypeSummary(TestTokens::company(), 'PNL', 'Winst & verlies'), $page->items[6]);
        self::assertSame(DimensionTypeCode::Projects, $page->items[7]->standardCode());

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertSame('DMT', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('2', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('21', $xpath->evaluate('string(//tw:Search/tw:firstRow)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame(0.0, $xpath->evaluate('count(//tw:Search/tw:options/tw:ArrayOfString)'), 'The company comes from the header.');
    }

    public function testSearchesByFinancialLevelAndDimension(): void
    {
        $http = ReplayClient::fixture('finder-dimension-types-deb');

        $this->twinfield($http)->dimensionTypes()->search()->financialLevel(2)->ofDimension('1000')->get();

        $xpath = $this->soapXpath($http->lastRequest());
        $option = static fn (int $index): string => sprintf('//tw:Search/tw:options/tw:ArrayOfString[%d]', $index);

        self::assertSame(2.0, $xpath->evaluate('count(//tw:Search/tw:options/tw:ArrayOfString)'));
        self::assertSame('finlevel', $xpath->evaluate('string(' . $option(1) . '/tw:string[1])'));
        self::assertSame('2', $xpath->evaluate('string(' . $option(1) . '/tw:string[2])'));
        self::assertSame('dim', $xpath->evaluate('string(' . $option(2) . '/tw:string[1])'));
        self::assertSame('1000', $xpath->evaluate('string(' . $option(2) . '/tw:string[2])'));
    }

    public function testChecksWhetherACodeExists(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-dimension-types-deb'), ReplayClient::response('finder-dimension-types-deb'));
        $types = $this->twinfield($http)->dimensionTypes();

        self::assertTrue($types->exists(DimensionTypeCode::AccountsReceivable));
        self::assertFalse($types->exists('DE'), 'Only an exact code counts.');

        $finder = $this->soapXpath($http->requests[0]);
        self::assertSame('DEB', $finder->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $finder->evaluate('string(//tw:Search/tw:field)'), 'Searches the code only.');
    }

    public function testReadsADimensionTypeInOneRequest(): void
    {
        $http = ReplayClient::fixture('dimension-type-read');

        $type = $this->twinfield($http)->dimensionTypes()->get(DimensionTypeCode::AccountsReceivable);

        self::assertCount(1, $http->requests);
        self::assertSame(
            '<dimensiontype><office>1000</office><code>DEB</code></dimensiontype>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertEquals(TestTokens::company('1000'), $type->company);
        self::assertSame('DEB', $type->code);
        self::assertSame(DimensionTypeCode::AccountsReceivable, $type->standardCode());
        self::assertSame('Debiteuren', $type->name);
        self::assertSame('Debiteuren', $type->shortName);
        self::assertSame('1[0-9][0-9][0-9]', $type->mask->pattern);
        self::assertTrue($type->mask->matches('1010'));
        self::assertFalse($type->mask->matches('2000'));
        self::assertEquals(new DimensionTypeLevels(financial: 2, time: 0, fixedAssets: 0, invoices: 0), $type->levels);
        self::assertEquals(new AddressLabels('Tav', 'Adres', 'Postadres', 'BTW nummer', 'KVK nummer', null), $type->addressLabels);
        self::assertSame(1, $type->touched);
    }

    public function testReadsATypeWithoutShortNameOrLabels(): void
    {
        $type = $this->twinfield(ReplayClient::fixture('dimension-type-read-ast'))->dimensionTypes()->get('AST');

        self::assertNull($type->shortName);
        self::assertSame([], $type->addressLabels->used());
        self::assertSame(1, $type->levels->fixedAssets);
        self::assertSame(3, $type->levels->financial);
    }

    public function testThrowsWhenTheDimensionTypeDoesNotExist(): void
    {
        $http = ReplayClient::fixture('dimension-type-read-not-found');

        try {
            $this->twinfield($http)->dimensionTypes()->get('XYZ');
            self::fail('Expected DimensionTypeNotFoundException.');
        } catch (DimensionTypeNotFoundException $exception) {
            self::assertSame('XYZ', $exception->dimensionTypeCode);
            self::assertSame('Dimensietype XYZ bestaat niet.', $exception->reason);
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testFindReturnsNullWhenTheDimensionTypeDoesNotExist(): void
    {
        self::assertNull($this->twinfield(ReplayClient::fixture('dimension-type-read-not-found'))->dimensionTypes()->find('XYZ'));
    }

    public function testUpdatesADimensionType(): void
    {
        $http = new ReplayClient(ReplayClient::response('dimension-type-read'), ReplayClient::response('dimension-type-save'));
        $types = $this->twinfield($http)->dimensionTypes();

        $updated = $types->update(
            $types->get('DEB')->edit()
                ->name('Klanten')
                ->mask('1[0-9][0-9][0-9][0-9]')
                ->addressLabel(6, 'IBAN'),
        );

        self::assertCount(2, $http->requests);
        self::assertSame(
            '<dimensiontype><office>1000</office><code>DEB</code><name>Klanten</name><mask>1[0-9][0-9][0-9][0-9]</mask>'
            . '<address><label1>Tav</label1><label2>Adres</label2><label3>Postadres</label3>'
            . '<label4>BTW nummer</label4><label5>KVK nummer</label5><label6>IBAN</label6></address>'
            . '</dimensiontype>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertSame('Klanten', $updated->name);
        self::assertSame('1[0-9][0-9][0-9][0-9]', $updated->mask->pattern);
        self::assertSame('IBAN', $updated->addressLabels->field6);
    }

    public function testThrowsTheRefusalWhenTwinfieldRefusesAnUpdate(): void
    {
        $http = new ReplayClient(ReplayClient::response('dimension-type-read'), ReplayClient::response('dimension-type-save-error'));
        $types = $this->twinfield($http)->dimensionTypes();

        try {
            $types->update($types->get('DEB')->edit()->addressLabel(6, 'IBAN'));
            self::fail('Expected ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame('dimensiontype/address/label6', $exception->result->errors()[0]->path);
        }
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Dimension types belong to a company: use company() first.');

        $twinfield->dimensionTypes()->get('DEB');
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
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
