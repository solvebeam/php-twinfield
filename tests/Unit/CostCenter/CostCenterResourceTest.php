<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\CostCenter;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\CostCenter\CostCenterInput;
use SolveBeam\Twinfield\CostCenter\CostCenterSummary;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class CostCenterResourceTest extends TestCase
{
    public function testSearchesCostCenters(): void
    {
        $http = ReplayClient::fixture('finder-cost-centers');

        $page = $this->twinfield($http)->costCenters()->search('1*')
            ->inCode()
            ->modifiedSince(new DateTimeImmutable('2025-03-14 09:30:15'))
            ->limit(10)
            ->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new CostCenterSummary(TestTokens::company(), '100', 'Sales & marketing'),
            new CostCenterSummary(TestTokens::company(), '200', 'Research'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('DIM', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('1*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame(['dimtype' => 'KPL', 'modifiedsince' => '20250314093015'], $this->finderOptions($xpath));
    }

    public function testSearchesAllPagesLazily(): void
    {
        $http = ReplayClient::fixture('finder-cost-centers');

        $codes = array_map(
            static fn (CostCenterSummary $costCenter): string => $costCenter->code,
            iterator_to_array($this->twinfield($http)->costCenters()->search()->all(), false),
        );

        self::assertSame(['100', '200'], $codes);
        self::assertCount(1, $http->requests);
    }

    public function testThrowsOnASearchRowWithoutName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Finder cost center row 3 has no code or name: ["100",""].');

        CostCenterSummary::fromFinderRow(['100', ''], 3, TestTokens::company());
    }

    public function testReadsACostCenter(): void
    {
        $http = ReplayClient::fixture('cost-center-read');

        $costCenter = $this->twinfield($http)->costCenters()->get('100');

        self::assertSame(
            '<read><type>dimensions</type><office>1000</office><dimtype>KPL</dimtype><code>100</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertEquals(TestTokens::company('1000'), $costCenter->company);
        self::assertSame('100', $costCenter->code);
        self::assertSame('5f3c2a1e-7b4d-4e8f-9a6b-1c2d3e4f5a6b', $costCenter->uid);
        self::assertSame('Sales & marketing', $costCenter->name);
        self::assertSame(DimensionStatus::Active, $costCenter->status);
        self::assertTrue($costCenter->inUse);
        self::assertSame(DimensionBehaviour::Normal, $costCenter->behaviour);
        self::assertSame(2, $costCenter->touched);
        self::assertEquals(new DateTimeImmutable('2025-03-14 09:30:15'), $costCenter->modified);
    }

    public function testReadsAHiddenCostCenter(): void
    {
        $costCenter = $this->twinfield(ReplayClient::fixture('cost-center-read-hidden'))->costCenters()->get('100');

        self::assertSame(DimensionStatus::Hidden, $costCenter->status);
        self::assertSame('Old department', $costCenter->name);
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('cost-center-read-not-found'))->costCenters()->get('999');
            self::fail('Expected a DimensionNotFoundException.');
        } catch (DimensionNotFoundException $exception) {
            self::assertSame('KPL', $exception->dimensionType);
            self::assertSame('999', $exception->dimensionCode);
            self::assertSame('Dimensie 999 bestaat niet.', $exception->reason);
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        self::assertNull(
            $this->twinfield(ReplayClient::fixture('cost-center-read-not-found'))->costCenters()->find('999'),
        );
    }

    public function testFindReturnsTheCostCenter(): void
    {
        self::assertSame(
            '100',
            $this->twinfield(ReplayClient::fixture('cost-center-read'))->costCenters()->find('100')?->code,
        );
    }

    public function testThrowsOnAnotherDimensionType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/type');

        $this->twinfield(ReplayClient::fixture('cost-center-read-wrong-type'))->costCenters()->get('100');
    }

    public function testThrowsOnUnknownBehaviour(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/behaviour is "magic", expected one of normal, system, template.');

        $this->twinfield(ReplayClient::fixture('cost-center-read-unknown-behaviour'))->costCenters()->get('100');
    }

    public function testSavesACostCenter(): void
    {
        $http = ReplayClient::fixture('cost-center-save');

        $costCenter = $this->twinfield($http)->costCenters()->save(CostCenterInput::create('200', 'R&D'));

        self::assertSame(
            '<dimension><office>1000</office><type>KPL</type><code>200</code><name>R&amp;D</name></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );

        // The save response of the documentation has no <modified>.
        self::assertSame('200', $costCenter->code);
        self::assertSame('Marketing', $costCenter->name);
        self::assertSame('9b2e4c6d-1a3f-4d5e-8b7c-0f1e2d3c4b5a', $costCenter->uid);
        self::assertFalse($costCenter->inUse);
        self::assertNull($costCenter->modified);
    }

    public function testThrowsTheTwinfieldErrorsWhenSavingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('cost-center-save-error'))->costCenters()->save(
                CostCenterInput::create('SALES', 'Sales'),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame(
                'dimension/code: Code voldoet niet aan het formaat [0-9][0-9][0-9].',
                $exception->getMessage(),
            );
        }
    }

    public function testCreatesAfterCheckingTheCodeIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('cost-center-read-not-found'),
            ReplayClient::response('cost-center-save'),
        );

        $costCenter = $this->twinfield($http)->costCenters()->create(CostCenterInput::create('200', 'Marketing'));

        self::assertSame('200', $costCenter->code);
        self::assertCount(2, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[0]));
        self::assertStringStartsWith('<dimension>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('cost-center-read');

        try {
            $this->twinfield($http)->costCenters()->create(CostCenterInput::create('100', 'Marketing'));
            self::fail('Expected a DimensionAlreadyExistsException.');
        } catch (DimensionAlreadyExistsException $exception) {
            self::assertSame('Dimension KPL 100 already exists.', $exception->getMessage());
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testUpdatesAnExistingCostCenter(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('cost-center-read'),
            ReplayClient::response('cost-center-read'),
            ReplayClient::response('cost-center-save'),
        );
        $costCenters = $this->twinfield($http)->costCenters();

        $costCenters->update($costCenters->get('100')->edit()->name('Sales'));

        self::assertCount(3, $http->requests);
        self::assertSame(
            '<dimension><office>1000</office><type>KPL</type><code>100</code><name>Sales</name></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testUpdateRefusesAMissingCostCenter(): void
    {
        $http = ReplayClient::fixture('cost-center-read-not-found');

        $this->expectException(DimensionNotFoundException::class);

        try {
            $this->twinfield($http)->costCenters()->update(CostCenterInput::create('999', 'Sales'));
        } finally {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testDeletes(): void
    {
        $http = ReplayClient::fixture('cost-center-delete');

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->costCenters()->delete('200'));
        self::assertSame(
            '<dimension status="deleted"><office>1000</office><type>KPL</type><code>200</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesACostCenterInUse(): void
    {
        self::assertSame(
            DeleteResult::Hidden,
            $this->twinfield(ReplayClient::fixture('cost-center-delete-hidden'))->costCenters()->delete('100'),
        );
    }

    public function testDeleteThrowsNotFound(): void
    {
        $this->expectException(DimensionNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('cost-center-read-not-found'))->costCenters()->delete('999');
    }

    public function testDeleteThrowsWhenTheCostCenterStaysActive(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Cost center 100 is still active after deleting it.');

        $this->twinfield(ReplayClient::fixture('cost-center-activate'))->costCenters()->delete('100');
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('cost-center-activate');

        $this->twinfield($http)->costCenters()->activate('100');

        self::assertSame(
            '<dimension status="active"><office>1000</office><type>KPL</type><code>100</code></dimension>',
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
        $this->expectExceptionMessage('Cost centers belong to a company: use company() first.');

        $twinfield->costCenters()->get('100');
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
            self::assertInstanceOf(\DOMElement::class, $option);

            $name = $xpath->evaluate('string(tw:string[1])', $option);
            $value = $xpath->evaluate('string(tw:string[2])', $option);

            self::assertIsString($name);
            self::assertIsString($value);

            $options[$name] = $value;
        }

        return $options;
    }
}
