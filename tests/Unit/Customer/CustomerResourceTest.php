<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Customer;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Customer\CustomerInput;
use SolveBeam\Twinfield\Customer\CustomerSummary;
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class CustomerResourceTest extends TestCase
{
    public function testSearchesCustomers(): void
    {
        $http = ReplayClient::fixture('finder-customers');

        $page = $this->twinfield($http)->customers()->search('NL91*')->inBankAccount()->limit(10)->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new CustomerSummary(TestTokens::company(), '1000', 'Example Trading & Co B.V.'),
            new CustomerSummary(TestTokens::company(), '1001', 'Sample Retail'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('DIM', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('NL91*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('3', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('DEB', $xpath->evaluate('string(//tw:Search/tw:options/tw:ArrayOfString[tw:string[1]="dimtype"]/tw:string[2])'));
    }

    public function testSearchesCustomersOfTheCompanyOption(): void
    {
        $http = ReplayClient::fixture('finder-customers');

        $page = $this->twinfield($http)->customers()->search()->option('company', '1001')->get();

        self::assertEquals(TestTokens::company('1001'), $page->items[0]->company);
    }

    public function testSearchNeedsACompany(): void
    {
        $http = ReplayClient::fixture('finder-customers');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('needs a company');

        try {
            $this->twinfield($http)->withoutCompany()->customers()->search()->get();
        } finally {
            self::assertCount(0, $http->requests, 'Without company, nothing is sent.');
        }
    }

    public function testSearchesInAddresses(): void
    {
        $http = ReplayClient::fixture('finder-customers');

        $this->twinfield($http)->customers()->search('*Amsterdam*')->inAddress()->get();

        self::assertSame('4', $this->soapXpath($http->lastRequest())->evaluate('string(//tw:Search/tw:field)'));
    }

    public function testThrowsOnASearchRowWithoutName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Finder customer row 3 has no code or name: ["1000",""].');

        CustomerSummary::fromFinderRow(['1000', ''], 3, TestTokens::company());
    }

    public function testReadsACustomer(): void
    {
        $http = ReplayClient::fixture('customer-read');

        $customer = $this->twinfield($http)->customers()->get('1000');

        self::assertSame(
            '<read><type>dimensions</type><office>1000</office><dimtype>DEB</dimtype><code>1000</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame('Example Trading & Co B.V.', $customer->name);
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('customer-read-not-found'))->customers()->get('9999');
            self::fail('Expected a DimensionNotFoundException.');
        } catch (DimensionNotFoundException $exception) {
            self::assertSame('DEB', $exception->dimensionType);
            self::assertSame('9999', $exception->dimensionCode);
            self::assertSame('Dimensie 9999 bestaat niet.', $exception->reason);
        }
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->twinfield(ReplayClient::fixture('customer-read-not-found'))->customers()->find('9999'));
    }

    public function testCreatesWithoutCodeInOneRequest(): void
    {
        $http = ReplayClient::fixture('customer-save');

        $customer = $this->twinfield($http)->customers()->create(
            CustomerInput::create('New Customer B.V.')
                ->dueDays(30)
                ->address(AddressInput::invoice('New Customer B.V.')->city('Utrecht')->postcode('3511 AA')->country('NL')->default()),
        );

        self::assertCount(1, $http->requests, 'No read: Twinfield gives the code.');
        self::assertStringNotContainsString('<code>', $this->xmlRequest($http->lastRequest()));

        // The save response has the new code, but no <modified>.
        self::assertSame('1006', $customer->code);
        self::assertSame('New Customer B.V.', $customer->name);
        self::assertSame(30, $customer->financials->dueDays);
        self::assertSame('Utrecht', $customer->defaultAddress()?->city);
        self::assertFalse($customer->inUse);
        self::assertNull($customer->modified);
    }

    public function testCreatesWithCodeAfterCheckingItIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('customer-read-not-found'),
            ReplayClient::response('customer-save'),
        );

        $this->twinfield($http)->customers()->create(CustomerInput::create('New Customer B.V.')->code('1006'));

        self::assertCount(2, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[0]));
        self::assertStringStartsWith('<dimension><office>1000</office><type>DEB</type><code>1006</code>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('customer-read');

        try {
            $this->twinfield($http)->customers()->create(CustomerInput::create('Duplicate')->code('1000'));
            self::fail('Expected a DimensionAlreadyExistsException.');
        } catch (DimensionAlreadyExistsException $exception) {
            self::assertSame('Dimension DEB 1000 already exists.', $exception->getMessage());
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testThrowsTheTwinfieldErrorsWhenSavingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('customer-save-error'))->customers()->save(
                CustomerInput::create('New Customer B.V.')->address(AddressInput::invoice()->vatNumber('NL123')->default()),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame('dimension/addresses/address/field4: Ongeldig btw-nummer.', $exception->getMessage());
        }
    }

    public function testUpdatesAnExistingCustomer(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('customer-read'),
            ReplayClient::response('customer-read'),
            ReplayClient::response('customer-read'),
        );
        $customers = $this->twinfield($http)->customers();

        $customers->update($customers->get('1000')->edit()->dueDays(14));

        self::assertCount(3, $http->requests);
        self::assertStringContainsString('<code>1000</code>', $this->xmlRequest($http->lastRequest()));
        self::assertStringContainsString('<duedays>14</duedays>', $this->xmlRequest($http->lastRequest()));
    }

    public function testUpdateNeedsACode(): void
    {
        $http = new ReplayClient();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Updating a customer needs its code: start from $customer->edit().');

        $this->twinfield($http)->customers()->update(CustomerInput::create('No code'));
    }

    public function testUpdateRefusesAMissingCustomer(): void
    {
        $http = ReplayClient::fixture('customer-read-not-found');

        $this->expectException(DimensionNotFoundException::class);

        try {
            $this->twinfield($http)->customers()->update(CustomerInput::create('Missing')->code('9999'));
        } finally {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testDeletes(): void
    {
        $http = ReplayClient::fixture('customer-delete');

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->customers()->delete('1006'));
        self::assertSame(
            '<dimension status="deleted"><office>1000</office><type>DEB</type><code>1006</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesACustomerInUse(): void
    {
        self::assertSame(
            DeleteResult::Hidden,
            $this->twinfield(ReplayClient::fixture('customer-delete-hidden'))->customers()->delete('1000'),
        );
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('customer-read');

        $this->twinfield($http)->customers()->activate('1000');

        self::assertSame(
            '<dimension status="active"><office>1000</office><type>DEB</type><code>1000</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testRequiresACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Customers belong to a company: use company() first.');

        $twinfield->customers()->get('1000');
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
