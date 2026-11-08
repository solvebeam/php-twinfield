<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Supplier;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Supplier\SupplierInput;
use SolveBeam\Twinfield\Supplier\SupplierSummary;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class SupplierResourceTest extends TestCase
{
    public function testSearchesSuppliers(): void
    {
        $http = ReplayClient::fixture('finder-suppliers');

        $page = $this->twinfield($http)->suppliers()->search('NL91*')->inBankAccount()->limit(10)->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new SupplierSummary('2000', 'Example Supplies & Co B.V.'),
            new SupplierSummary('2001', 'Sample Wholesale'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('DIM', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('NL91*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('3', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('CRD', $xpath->evaluate('string(//tw:Search/tw:options/tw:ArrayOfString[tw:string[1]="dimtype"]/tw:string[2])'));
    }

    public function testSearchesInAddresses(): void
    {
        $http = ReplayClient::fixture('finder-suppliers');

        $this->twinfield($http)->suppliers()->search('*Amsterdam*')->inAddress()->get();

        self::assertSame('4', $this->soapXpath($http->lastRequest())->evaluate('string(//tw:Search/tw:field)'));
    }

    public function testThrowsOnASearchRowWithoutName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Finder supplier row 3 has no code or name: ["2000",""].');

        SupplierSummary::fromFinderRow(['2000', ''], 3);
    }

    public function testReadsASupplier(): void
    {
        $http = ReplayClient::fixture('supplier-read');

        $supplier = $this->twinfield($http)->suppliers()->get('2000');

        self::assertSame(
            '<read><type>dimensions</type><office>1000</office><dimtype>CRD</dimtype><code>2000</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame('Example Supplies B.V.', $supplier->name);
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('supplier-read-not-found'))->suppliers()->get('9999');
            self::fail('Expected a DimensionNotFoundException.');
        } catch (DimensionNotFoundException $exception) {
            self::assertSame('CRD', $exception->dimensionType);
            self::assertSame('9999', $exception->dimensionCode);
            self::assertSame('Dimensie 9999 bestaat niet.', $exception->reason);
        }
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->twinfield(ReplayClient::fixture('supplier-read-not-found'))->suppliers()->find('9999'));
    }

    public function testCreatesWithoutCodeInOneRequest(): void
    {
        $http = ReplayClient::fixture('supplier-save');

        $supplier = $this->twinfield($http)->suppliers()->create(
            SupplierInput::create('New Supplier B.V.')
                ->dueDays(30)
                ->creditTransfer('SEPANLCT')
                ->bank(BankInput::create()->iban('NL91ABNA0417164300')->ascription('New Supplier B.V.')->default()),
        );

        self::assertCount(1, $http->requests, 'No read: Twinfield gives the code.');
        self::assertStringNotContainsString('<code>', $this->xmlRequest($http->lastRequest()));

        // The save response has the new code, but no <modified>.
        self::assertSame('2010', $supplier->code);
        self::assertSame('New Supplier B.V.', $supplier->name);
        self::assertSame(30, $supplier->financials->dueDays);
        self::assertSame('SEPANLCT', $supplier->financials->payCode);
        self::assertSame('NL91ABNA0417164300', $supplier->defaultBank()?->iban);
        self::assertFalse($supplier->inUse);
        self::assertNull($supplier->modified);
    }

    public function testCreatesWithCodeAfterCheckingItIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('supplier-read-not-found'),
            ReplayClient::response('supplier-save'),
        );

        $this->twinfield($http)->suppliers()->create(SupplierInput::create('New Supplier B.V.')->code('2010'));

        self::assertCount(2, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[0]));
        self::assertStringStartsWith('<dimension><office>1000</office><type>CRD</type><code>2010</code>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('supplier-read');

        try {
            $this->twinfield($http)->suppliers()->create(SupplierInput::create('Duplicate')->code('2000'));
            self::fail('Expected a DimensionAlreadyExistsException.');
        } catch (DimensionAlreadyExistsException $exception) {
            self::assertSame('Dimension CRD 2000 already exists.', $exception->getMessage());
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testThrowsTheTwinfieldErrorsWhenSavingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('supplier-save-error'))->suppliers()->save(
                SupplierInput::create('New Supplier B.V.')->creditTransfer('UNKNOWN'),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame('dimension/financials/paycode: Betaalwijze UNKNOWN bestaat niet.', $exception->getMessage());
        }
    }

    public function testUpdatesAnExistingSupplier(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('supplier-read'),
            ReplayClient::response('supplier-read'),
            ReplayClient::response('supplier-read'),
        );
        $suppliers = $this->twinfield($http)->suppliers();

        $suppliers->update($suppliers->get('2000')->edit()->dueDays(14));

        self::assertCount(3, $http->requests);
        self::assertStringContainsString('<code>2000</code>', $this->xmlRequest($http->lastRequest()));
        self::assertStringContainsString('<duedays>14</duedays>', $this->xmlRequest($http->lastRequest()));
    }

    public function testUpdateNeedsACode(): void
    {
        $http = new ReplayClient();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Updating a supplier needs its code: start from $supplier->edit().');

        $this->twinfield($http)->suppliers()->update(SupplierInput::create('No code'));
    }

    public function testUpdateRefusesAMissingSupplier(): void
    {
        $http = ReplayClient::fixture('supplier-read-not-found');

        $this->expectException(DimensionNotFoundException::class);

        try {
            $this->twinfield($http)->suppliers()->update(SupplierInput::create('Missing')->code('9999'));
        } finally {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testDeletes(): void
    {
        $http = ReplayClient::fixture('supplier-delete');

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->suppliers()->delete('2010'));
        self::assertSame(
            '<dimension status="deleted"><office>1000</office><type>CRD</type><code>2010</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesASupplierInUse(): void
    {
        self::assertSame(
            DeleteResult::Hidden,
            $this->twinfield(ReplayClient::fixture('supplier-delete-hidden'))->suppliers()->delete('2000'),
        );
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('supplier-read');

        $this->twinfield($http)->suppliers()->activate('2000');

        self::assertSame(
            '<dimension status="active"><office>1000</office><type>CRD</type><code>2000</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testRequiresACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(new StaticAccessTokenProvider('token-123', 'https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Suppliers belong to a company: use company() first.');

        $twinfield->suppliers()->get('2000');
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
