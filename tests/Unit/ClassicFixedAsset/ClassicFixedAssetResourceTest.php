<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ClassicFixedAsset;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAssetInput;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAssetStatus;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAssetSummary;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class ClassicFixedAssetResourceTest extends TestCase
{
    public function testSearchesFixedAssets(): void
    {
        $http = ReplayClient::fixture('finder-fixed-assets');

        $page = $this->twinfield($http)->classicFixedAssets()->search('6*')
            ->inCode()
            ->group('VEHICLES')
            ->modifiedSince(new DateTimeImmutable('2026-03-14 09:30:15'))
            ->limit(10)
            ->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new ClassicFixedAssetSummary(TestTokens::company(), '60010', 'Delivery van'),
            new ClassicFixedAssetSummary(TestTokens::company(), '60011', 'Electric van & charger'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('DIM', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('6*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame(
            ['dimtype' => 'AST', 'group' => 'VEHICLES', 'modifiedsince' => '20260314093015'],
            $this->finderOptions($xpath),
        );
    }

    public function testThrowsOnASearchRowWithoutName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Finder fixed asset row 3 has no code or name: ["60010",""].');

        ClassicFixedAssetSummary::fromFinderRow(['60010', ''], 3, TestTokens::company());
    }

    public function testReadsAFixedAsset(): void
    {
        $http = ReplayClient::fixture('fixed-asset-read');

        $asset = $this->twinfield($http)->classicFixedAssets()->get('60010');

        self::assertSame(
            '<read><type>dimensions</type><office>1000</office><dimtype>AST</dimtype><code>60010</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame('60010', $asset->code);
        self::assertSame(ClassicFixedAssetStatus::Active, $asset->assetStatus);
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('fixed-asset-read-not-found'))->classicFixedAssets()->get('69999');
            self::fail('Expected a DimensionNotFoundException.');
        } catch (DimensionNotFoundException $exception) {
            self::assertSame('AST', $exception->dimensionType);
            self::assertSame('69999', $exception->dimensionCode);
            self::assertSame('Dimensie 69999 bestaat niet.', $exception->reason);
        }
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        self::assertNull(
            $this->twinfield(ReplayClient::fixture('fixed-asset-read-not-found'))->classicFixedAssets()->find('69999'),
        );
    }

    public function testSavesAFixedAsset(): void
    {
        $http = ReplayClient::fixture('fixed-asset-save');

        $asset = $this->twinfield($http)->classicFixedAssets()->save(
            ClassicFixedAssetInput::create('Delivery van')
                ->method('5YVEH')
                ->costCenter('00000')
                ->purchaseDate(new DateTimeImmutable('2026-03-15'))
                ->depreciateFrom(Period::of(2026, 4)),
        );

        self::assertSame(
            '<dimension><office>1000</office><type>AST</type><name>Delivery van</name>'
            . '<financials><substitutionlevel>2</substitutionlevel><substitutewith>00000</substitutewith></financials>'
            . '<fixedassets><method>5YVEH</method><beginperiod>2026/04</beginperiod>'
            . '<purchasedate>20260315</purchasedate></fixedassets></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame('60011', $asset->code, 'Twinfield gives the next free code.');
        self::assertSame(ClassicFixedAssetStatus::ToBeActivated, $asset->assetStatus);
    }

    public function testThrowsTheTwinfieldErrorsWhenSavingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('fixed-asset-save-error'))->classicFixedAssets()->save(
                ClassicFixedAssetInput::create('Delivery van')->method('NOPE'),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame(
                'dimension/fixedassets/method: Afschrijvingsmethode NOPE bestaat niet.',
                $exception->getMessage(),
            );
        }
    }

    public function testCreatesWithoutCodeInOneRequest(): void
    {
        $http = ReplayClient::fixture('fixed-asset-save');

        $this->twinfield($http)->classicFixedAssets()->create(ClassicFixedAssetInput::create('Delivery van'));

        self::assertCount(1, $http->requests, 'Without code there is nothing to check.');
    }

    public function testCreatesAfterCheckingTheCodeIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('fixed-asset-read-not-found'),
            ReplayClient::response('fixed-asset-save'),
        );

        $this->twinfield($http)->classicFixedAssets()->create(
            ClassicFixedAssetInput::create('Delivery van')->code('60011'),
        );

        self::assertCount(2, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[0]));
        self::assertStringStartsWith('<dimension>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('fixed-asset-read');

        try {
            $this->twinfield($http)->classicFixedAssets()->create(ClassicFixedAssetInput::create('Van')->code('60010'));
            self::fail('Expected a DimensionAlreadyExistsException.');
        } catch (DimensionAlreadyExistsException $exception) {
            self::assertSame('Dimension AST 60010 already exists.', $exception->getMessage());
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testUpdatesAnExistingFixedAsset(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('fixed-asset-read'),
            ReplayClient::response('fixed-asset-read'),
            ReplayClient::response('fixed-asset-save'),
        );
        $fixedAssets = $this->twinfield($http)->classicFixedAssets();

        $fixedAssets->update($fixedAssets->get('60010')->edit()->residualValue('1000'));

        self::assertCount(3, $http->requests);
        self::assertStringContainsString(
            '<residualvalue>1000.00</residualvalue>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertStringNotContainsString('<beginperiod>', $this->xmlRequest($http->lastRequest()), 'Locked.');
    }

    public function testUpdateNeedsACode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Updating a fixed asset needs its code: start from $asset->edit().');

        $this->twinfield(new ReplayClient())->classicFixedAssets()->update(ClassicFixedAssetInput::create('Van'));
    }

    public function testUpdateRefusesAMissingFixedAsset(): void
    {
        $http = ReplayClient::fixture('fixed-asset-read-not-found');

        $this->expectException(DimensionNotFoundException::class);

        try {
            $this->twinfield($http)->classicFixedAssets()->update(ClassicFixedAssetInput::create('Van')->code('69999'));
        } finally {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testDeletes(): void
    {
        $http = ReplayClient::fixture('fixed-asset-delete');

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->classicFixedAssets()->delete('60011'));
        self::assertSame(
            '<dimension status="deleted"><office>1000</office><type>AST</type><code>60011</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesAFixedAssetInUse(): void
    {
        self::assertSame(
            DeleteResult::Hidden,
            $this->twinfield(ReplayClient::fixture('fixed-asset-delete-hidden'))->classicFixedAssets()->delete('60010'),
        );
    }

    public function testDeleteThrowsWhenTheFixedAssetStaysActive(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Fixed asset 60010 is still active after deleting it.');

        $this->twinfield(ReplayClient::fixture('fixed-asset-activate'))->classicFixedAssets()->delete('60010');
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('fixed-asset-activate');

        $this->twinfield($http)->classicFixedAssets()->activate('60010');

        self::assertSame(
            '<dimension status="active"><office>1000</office><type>AST</type><code>60010</code></dimension>',
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
        $this->expectExceptionMessage('Fixed assets belong to a company: use company() first.');

        $twinfield->classicFixedAssets()->get('60010');
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

    /**
     * @return array<string, string>
     */
    private function finderOptions(DOMXPath $xpath): array
    {
        $options = [];
        $nodes = $xpath->query('//tw:Search/tw:options/tw:ArrayOfString');

        foreach ($nodes === false ? [] : $nodes as $option) {
            $name = $xpath->evaluate('string(tw:string[1])', $option);
            $value = $xpath->evaluate('string(tw:string[2])', $option);

            self::assertIsString($name);
            self::assertIsString($value);

            $options[$name] = $value;
        }

        return $options;
    }
}
