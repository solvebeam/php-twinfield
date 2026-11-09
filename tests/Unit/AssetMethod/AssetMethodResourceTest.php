<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\AssetMethod;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\AssetMethod\AccountReference;
use SolveBeam\Twinfield\AssetMethod\AssetMethodAlreadyExistsException;
use SolveBeam\Twinfield\AssetMethod\AssetMethodInput;
use SolveBeam\Twinfield\AssetMethod\AssetMethodNotFoundException;
use SolveBeam\Twinfield\AssetMethod\AssetMethodStatus;
use SolveBeam\Twinfield\AssetMethod\AssetMethodSummary;
use SolveBeam\Twinfield\AssetMethod\CalculationMethod;
use SolveBeam\Twinfield\AssetMethod\DepreciationStart;
use SolveBeam\Twinfield\AssetMethod\FreeTextField;
use SolveBeam\Twinfield\AssetMethod\FreeTextType;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class AssetMethodResourceTest extends TestCase
{
    public function testSearchesAssetMethods(): void
    {
        $http = ReplayClient::fixture('finder-asset-methods');

        $page = $this->twinfield($http)->assetMethods()->search('MA*')->inName()->limit(10)->offset(20)->get();

        self::assertSame(2, $page->totalRows);
        self::assertCount(2, $page);
        self::assertEquals([
            new AssetMethodSummary(TestTokens::company(), 'CARS', 'Cars reducing balance'),
            new AssetMethodSummary(TestTokens::company(), 'MACHINE', 'Machines 5 jaar lineair'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertSame('ASM', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('MA*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('2', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('21', $xpath->evaluate('string(//tw:Search/tw:firstRow)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame(0.0, $xpath->evaluate('count(//tw:Search/tw:options/tw:ArrayOfString)'), 'The company comes from the header.');
    }

    public function testSearchIsImmutableAndSearchesCodeAndNameByDefault(): void
    {
        $search = $this->twinfield(new ReplayClient())->assetMethods()->search();

        self::assertSame(0, $search->finderQuery()->toSearch()->field);
        self::assertSame(1, $search->inCode()->finderQuery()->toSearch()->field);
        self::assertSame(0, $search->inName()->inCodeAndName()->finderQuery()->toSearch()->field);
        self::assertSame('*', $search->finderQuery()->toSearch()->pattern);
    }

    public function testSearchesAllAndCounts(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-asset-methods'), ReplayClient::response('finder-asset-methods'));
        $search = $this->twinfield($http)->assetMethods()->search();

        self::assertSame(['CARS', 'MACHINE'], array_map(
            static fn (AssetMethodSummary $summary): string => $summary->code,
            iterator_to_array($search->all(), false),
        ));
        self::assertSame(2, $search->count());
        self::assertSame('1', $this->soapXpath($http->lastRequest())->evaluate('string(//tw:Search/tw:maxRows)'));
    }

    public function testReadsALinearAssetMethod(): void
    {
        $http = ReplayClient::fixture('asset-method-read');

        $method = $this->twinfield($http)->assetMethods()->get('MACHINE');

        self::assertSame(
            '<assetmethod><office>1000</office><code>MACHINE</code></assetmethod>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertEquals(TestTokens::company('1000'), $method->company);
        self::assertSame('MACHINE', $method->code);
        self::assertSame('Machines 5 jaar lineair', $method->name);
        self::assertSame('Machines', $method->shortName);
        self::assertSame(AssetMethodStatus::Active, $method->status);
        self::assertTrue($method->inUse);
        self::assertEquals(new DateTimeImmutable('2024-01-15 09:30:00'), $method->created);
        self::assertEquals(new DateTimeImmutable('2025-03-14 09:30:15'), $method->modified);
        self::assertSame(3, $method->touched);
        self::assertSame('SUPER', $method->modifiedBy);
        self::assertSame(CalculationMethod::Linear, $method->calculationMethod);
        self::assertSame(60, $method->periods);
        self::assertNull($method->percentage);
        self::assertSame(DepreciationStart::FromReconciliation, $method->depreciationStart);

        $balance = $method->balanceAccounts;
        self::assertNull($balance->purchaseValueGroup);
        self::assertEquals(new AccountReference('0501', 'Machines purchase value', null, 'BAS'), $balance->purchaseValue);
        self::assertNull($balance->depreciationGroup);
        self::assertEquals(new AccountReference('0135', 'Machines cumulative depreciation', 'Cum. depr.', 'BAS'), $balance->depreciation);
        self::assertSame('1701', $balance->assetsToActivate?->code);
        self::assertNull($balance->reconciliation);
        self::assertSame('1702', $balance->toBeInvoiced?->code);

        $profitLoss = $method->profitLossAccounts;
        self::assertEquals(new AccountReference('4730', 'Depreciation costs machines', null, 'PNL'), $profitLoss->depreciation);
        self::assertSame('4980', $profitLoss->sales?->code);
        self::assertNull($profitLoss->reconciliation);

        self::assertCount(5, $method->freeTexts);
        self::assertEquals(new FreeTextField(1, FreeTextType::Text, 'Serial number'), $method->freeText(1));
        self::assertEquals(new FreeTextField(5, FreeTextType::Text, null), $method->freeText(5));
    }

    public function testReadsAPercentageAssetMethodWithAGroup(): void
    {
        $method = $this->twinfield(ReplayClient::fixture('asset-method-read-percentage'))->assetMethods()->get('CARS');

        self::assertFalse($method->inUse);
        self::assertNull($method->shortName);
        self::assertSame(CalculationMethod::ReducingBalance, $method->calculationMethod);
        self::assertSame('12.5', $method->percentage);
        self::assertSame(DepreciationStart::FromPurchaseDate, $method->depreciationStart);
        self::assertEquals(new AccountReference('VEHICLES', 'Vehicles', 'Veh.', null), $method->balanceAccounts->purchaseValueGroup);
        self::assertNull($method->balanceAccounts->purchaseValue);
        self::assertEquals(new FreeTextField(2, FreeTextType::Amount, 'Catalogue value'), $method->freeText(2));
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('asset-method-read-not-found'))->assetMethods()->get('M999');
            self::fail('Expected an AssetMethodNotFoundException.');
        } catch (AssetMethodNotFoundException $exception) {
            self::assertSame('M999', $exception->assetMethodCode);
            self::assertSame('Methode M999 bestaat niet.', $exception->reason);
            self::assertSame('Asset method M999 not found: Methode M999 bestaat niet.', $exception->getMessage());
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testFind(): void
    {
        self::assertNull($this->twinfield(ReplayClient::fixture('asset-method-read-not-found'))->assetMethods()->find('M999'));
        self::assertSame('MACHINE', $this->twinfield(ReplayClient::fixture('asset-method-read'))->assetMethods()->find('MACHINE')?->code);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unexpectedResponses(): iterable
    {
        yield 'unknown calculation method' => ['<calcmethod>linear</calcmethod>', '<calcmethod>sum</calcmethod>', 'assetmethod/calcmethod is "sum"'];
        yield 'unknown start' => ['from_reconciliation', 'from_tomorrow', 'assetmethod/depreciatereconciliation is "from_tomorrow"'];
        yield 'unknown status' => ['status="active"', 'status="archived"', 'assetmethod/@status is "archived"'];
        yield 'missing inuse' => ['inuse="true" ', '', 'assetmethod/@inuse is missing'];
        yield 'invalid percentage' => ['<percentage/>', '<percentage>12,5</percentage>', 'assetmethod/percentage is "12,5"'];
        yield 'account without name' => ['name="Machines purchase value"', 'name=""', 'assetmethod/balanceaccounts/purchasevalue/@name is empty'];
        yield 'missing free text field' => ['<freetext id="5" type="text"/>', '', 'freetexts has the fields 1, 2, 3, 4, expected 1 to 5'];
        yield 'missing accounts' => ['<profitlossaccounts>', '<other>', 'assetmethod/profitlossaccounts is missing'];
    }

    #[DataProvider('unexpectedResponses')]
    public function testThrowsOnUnexpectedResponses(string $search, string $replace, string $message): void
    {
        $fixture = (string) ReplayClient::response('asset-method-read')->getBody();
        $xml = str_replace(htmlspecialchars($search, ENT_NOQUOTES), htmlspecialchars($replace, ENT_NOQUOTES), $fixture);

        if ($search === '<profitlossaccounts>') {
            $xml = str_replace('&lt;/profitlossaccounts&gt;', '&lt;/other&gt;', $xml);
        }

        self::assertNotSame($fixture, $xml, 'The fixture must contain the text to replace.');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($message);

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->assetMethods()->get('MACHINE');
    }

    public function testSavesAndReadsBack(): void
    {
        $http = new ReplayClient(ReplayClient::response('asset-method-save'), ReplayClient::response('asset-method-read'));

        $method = $this->twinfield($http)->assetMethods()->save(
            AssetMethodInput::create('MACHINE', 'Machines 5 jaar lineair')->linear(60)->purchaseValueAccount('0501'),
        );

        self::assertTrue($method->inUse, 'The method is read back after saving.');
        self::assertCount(2, $http->requests);
        self::assertStringContainsString('<name>Machines 5 jaar lineair</name>', $this->xmlRequest($http->requests[0]));
        self::assertSame(
            '<assetmethod><office>1000</office><code>MACHINE</code></assetmethod>',
            $this->xmlRequest($http->requests[1]),
        );
    }

    public function testThrowsTheTwinfieldErrorsWhenSavingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('asset-method-save-error'))->assetMethods()->save(
                AssetMethodInput::create('MACHINE', 'Machines')->purchaseValueAccount('9999'),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            $errors = $exception->result->errors();

            self::assertCount(1, $errors);
            self::assertSame('assetmethod/balanceaccounts/purchasevalue', $errors[0]->path);
            self::assertSame('Grootboekrekening 9999 bestaat niet.', $errors[0]->text);
        }
    }

    public function testCreatesAfterCheckingTheCodeIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('asset-method-read-not-found'),
            ReplayClient::response('asset-method-save'),
            ReplayClient::response('asset-method-read'),
        );

        $method = $this->twinfield($http)->assetMethods()->create(AssetMethodInput::create('MACHINE', 'Machines')->linear(60));

        self::assertSame('MACHINE', $method->code);
        self::assertCount(3, $http->requests);
        self::assertStringContainsString('<calcmethod>linear</calcmethod>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('asset-method-read');

        try {
            $this->twinfield($http)->assetMethods()->create(AssetMethodInput::create('MACHINE', 'Machines'));
            self::fail('Expected an AssetMethodAlreadyExistsException.');
        } catch (AssetMethodAlreadyExistsException $exception) {
            self::assertSame('Asset method MACHINE already exists.', $exception->getMessage());
            self::assertSame('MACHINE', $exception->assetMethodCode);
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testUpdatesAnExistingAssetMethod(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('asset-method-read'),
            ReplayClient::response('asset-method-save'),
            ReplayClient::response('asset-method-read'),
        );
        $assetMethods = $this->twinfield($http)->assetMethods();

        $assetMethods->update(AssetMethodInput::create('MACHINE', 'Machines'));

        self::assertCount(3, $http->requests);
        self::assertStringContainsString('<name>Machines</name>', $this->xmlRequest($http->requests[1]));
    }

    public function testUpdateRefusesAMissingAssetMethod(): void
    {
        $http = ReplayClient::fixture('asset-method-read-not-found');

        $this->expectException(AssetMethodNotFoundException::class);

        try {
            $this->twinfield($http)->assetMethods()->update(AssetMethodInput::create('M999', 'Missing'));
        } finally {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testEditKeepsAllValues(): void
    {
        $assetMethods = $this->twinfield(ReplayClient::fixture('asset-method-read'))->assetMethods();
        $request = $assetMethods->get('MACHINE')->edit()->toXml('1000')->toXml();

        self::assertSame(
            '<assetmethod><office>1000</office><code>MACHINE</code><name>Machines 5 jaar lineair</name>'
            . '<shortname>Machines</shortname><calcmethod>linear</calcmethod><nrofperiods>60</nrofperiods>'
            . '<depreciatereconciliation>from_reconciliation</depreciatereconciliation>'
            . '<balanceaccounts><purchasevaluegroup/><purchasevalue>0501</purchasevalue><depreciationgroup/>'
            . '<depreciation>0135</depreciation><assetstoactivate>1701</assetstoactivate><reconciliation/>'
            . '<tobeinvoiced>1702</tobeinvoiced></balanceaccounts>'
            . '<profitlossaccounts><depreciation>4730</depreciation><sales>4980</sales><reconciliation/></profitlossaccounts>'
            . '<freetexts><freetext id="1" type="text">Serial number</freetext><freetext id="2" type="text"/>'
            . '<freetext id="3" type="text"/><freetext id="4" type="text"/><freetext id="5" type="text"/></freetexts>'
            . '</assetmethod>',
            $request,
        );
    }

    public function testEditKeepsThePercentageAndGroup(): void
    {
        $assetMethods = $this->twinfield(ReplayClient::fixture('asset-method-read-percentage'))->assetMethods();
        $request = $assetMethods->get('CARS')->edit()->toXml('1000')->toXml();

        self::assertStringContainsString('<calcmethod>reducebalance</calcmethod><percentage>12.5</percentage>', $request);
        self::assertStringNotContainsString('nrofperiods', $request, 'Not sent, so Twinfield keeps it.');
        self::assertStringContainsString('<purchasevaluegroup>VEHICLES</purchasevaluegroup>', $request);
        self::assertStringContainsString('<freetext id="2" type="amount">Catalogue value</freetext>', $request);
    }

    public function testDeletes(): void
    {
        $http = ReplayClient::fixture('asset-method-delete');

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->assetMethods()->delete('MACHINE'));
        self::assertSame(
            '<assetmethod status="deleted"><office>1000</office><code>MACHINE</code></assetmethod>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesAnAssetMethodInUse(): void
    {
        self::assertSame(
            DeleteResult::Hidden,
            $this->twinfield(ReplayClient::fixture('asset-method-delete-hidden'))->assetMethods()->delete('MACHINE'),
        );
    }

    public function testDeleteThrowsNotFound(): void
    {
        $this->expectException(AssetMethodNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('asset-method-read-not-found'))->assetMethods()->delete('M999');
    }

    public function testDeleteThrowsWhenTheAssetMethodStaysActive(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Asset method MACHINE is still active after deleting it.');

        $this->twinfield(ReplayClient::fixture('asset-method-activate'))->assetMethods()->delete('MACHINE');
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('asset-method-activate');

        $this->twinfield($http)->assetMethods()->activate('MACHINE');

        self::assertSame(
            '<assetmethod status="active"><office>1000</office><code>MACHINE</code></assetmethod>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testRequiresACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Asset methods belong to a company: use company() first.');

        $twinfield->assetMethods()->get('MACHINE');
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
