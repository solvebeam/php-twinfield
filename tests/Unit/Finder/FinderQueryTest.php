<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Finder;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Finder\FinderErrorCode;
use SolveBeam\Twinfield\Finder\FinderException;
use SolveBeam\Twinfield\Finder\FinderMessageType;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;

final class FinderQueryTest extends TestCase
{
    public function testSendsSearchWithAllParameters(): void
    {
        $http = ReplayClient::fixture('finder-search-dim');

        $result = $this->twinfield($http)
            ->company('1002')
            ->finder(FinderType::Dimensions)
            ->pattern('13*')
            ->field(2)
            ->option('dimtype', 'DEB')
            ->offset(20)
            ->limit(10)
            ->get();

        $xpath = $this->xpath($http->lastRequest());

        self::assertSame('DIM', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('13*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('2', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('21', $xpath->evaluate('string(//tw:Search/tw:firstRow)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame('dimtype', $xpath->evaluate('string(//tw:options/tw:ArrayOfString/tw:string[1])'));
        self::assertSame('DEB', $xpath->evaluate('string(//tw:options/tw:ArrayOfString/tw:string[2])'));
        self::assertSame('1002', $xpath->evaluate('string(//tw:Header/tw:CompanyCode)'));
        self::assertSame('token-123', $xpath->evaluate('string(//tw:Header/tw:AccessToken)'));
        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());

        self::assertSame(2, $result->totalRows);
        self::assertSame(['Code', 'Naam'], $result->columns);
        self::assertSame([['1000', 'Voorbeeld BV'], ['1010', 'Test & Co']], $result->rows);
        self::assertCount(2, $result);
    }

    public function testOmitsCompanyCodeAndOptionsWhenNotSet(): void
    {
        $http = ReplayClient::fixture('finder-search-dim');

        $this->twinfield($http)->finder(FinderType::Offices)->get();

        $xpath = $this->xpath($http->lastRequest());

        self::assertSame(0.0, $xpath->evaluate('count(//tw:Header/tw:CompanyCode)'));
        self::assertSame(0.0, $xpath->evaluate('count(//tw:Search/tw:options)'));
        self::assertSame('*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $xpath->evaluate('string(//tw:Search/tw:firstRow)'));
        self::assertSame('100', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
    }

    public function testIsImmutable(): void
    {
        $base = $this->twinfield(new ReplayClient())->finder(FinderType::Dimensions);
        $changed = $base->pattern('13*')->option('dimtype', 'DEB')->limit(5);

        self::assertNotSame($base, $changed);
        self::assertSame('*', $base->toSearch()->pattern);
        self::assertNull($base->toSearch()->options);
        self::assertSame(100, $base->toSearch()->maxRows);
        self::assertSame('13*', $changed->toSearch()->pattern);
    }

    public function testSettingAnOptionAgainReplacesIt(): void
    {
        $search = $this->twinfield(new ReplayClient())
            ->finder(FinderType::Dimensions)
            ->option('dimtype', 'DEB')
            ->option('dimtype', 'CRD')
            ->toSearch();

        self::assertCount(1, $search->options->items ?? []);
        self::assertSame(['dimtype', 'CRD'], $search->options->items[0]->items ?? null);
    }

    public function testAllFetchesEveryPage(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-offices-page-1'),
            ReplayClient::response('finder-offices-page-2'),
        );

        $rows = iterator_to_array($this->twinfield($http)->finder(FinderType::Offices)->limit(2)->all());

        self::assertSame(['1000', '1001', '1002'], array_column($rows, 0));
        self::assertCount(2, $http->requests, 'Stops after the last page.');
        self::assertSame('3', $this->xpath($http->requests[1])->evaluate('string(//tw:Search/tw:firstRow)'));
    }

    public function testAllIsLazy(): void
    {
        $http = new ReplayClient(ReplayClient::response('finder-offices-page-1'));

        $generator = $this->twinfield($http)->finder(FinderType::Offices)->limit(2)->all();

        self::assertCount(0, $http->requests, 'Nothing is fetched before iterating.');
        self::assertSame(['1000', 'Example Holding B.V.'], $generator->current());
        self::assertCount(1, $http->requests);
    }

    public function testMapsRecordedResponse(): void
    {
        // Anonymized recording of a real Twinfield response: 100 of 247 offices, 5 columns.
        $result = $this->twinfield(ReplayClient::fixture('search-searches-offices'))
            ->finder(FinderType::Offices)
            ->get();

        self::assertSame(247, $result->totalRows);
        self::assertCount(100, $result);
        self::assertCount(5, $result->columns);
        self::assertCount(5, $result->rows[0]);
    }

    public function testSearchesInTheCompanyOfTheClient(): void
    {
        $query = $this->twinfield(new ReplayClient())->company('1002')->finder(FinderType::Dimensions);

        self::assertEquals(TestTokens::company('1002'), $query->companyReference());
    }

    public function testSearchesInTheCompanyOfTheCompanyOption(): void
    {
        $query = $this->twinfield(new ReplayClient())->company('1002')->finder(FinderType::Dimensions);

        self::assertEquals(TestTokens::company('1003'), $query->option('company', '1003')->companyReference());
        self::assertEquals(
            TestTokens::company('1004'),
            $query->option('office', '1004')->companyReference(),
            'Deprecated office option.',
        );
    }

    public function testCompanyReferenceNeedsACompany(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Finder search DIM needs a company');

        $this->twinfield(new ReplayClient())->finder(FinderType::Dimensions)->companyReference();
    }

    public function testCompanyOption(): void
    {
        $http = ReplayClient::fixture('finder-search-dim');
        $query = $this->twinfield($http)->company('1002')->finder(FinderType::Dimensions)->company('1003');

        $query->get();

        $xpath = $this->xpath($http->lastRequest());

        self::assertSame('company', $xpath->evaluate('string(//tw:options/tw:ArrayOfString/tw:string[1])'));
        self::assertSame('1003', $xpath->evaluate('string(//tw:options/tw:ArrayOfString/tw:string[2])'));
        self::assertEquals(TestTokens::company('1003'), $query->companyReference());
        self::assertFalse($query->isAllCompanies());
    }

    public function testRefusesCompanyAndOfficeTogether(): void
    {
        $query = $this->twinfield(new ReplayClient())->finder(FinderType::Dimensions)->option('office', '1002');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('company and office cannot be used together');

        $query->company('1003');
    }

    public function testRefusesOfficeAfterCompany(): void
    {
        $query = $this->twinfield(new ReplayClient())->finder(FinderType::Dimensions)->company('1002');

        $this->expectException(InvalidArgumentException::class);

        $query->option('office', '1003');
    }

    public function testAllCompaniesHasNoCompanyReference(): void
    {
        $query = $this->twinfield(new ReplayClient())->company('1002')->finder(FinderType::VatCodes)->allCompanies();

        self::assertTrue($query->isAllCompanies());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('each row has its own company');

        $query->companyReference();
    }

    public function testThrowsTheMessagesOfARefusedSearch(): void
    {
        $query = $this->twinfield(ReplayClient::fixture('finder-error-option-not-allowed'))
            ->finder(FinderType::Dimensions)
            ->option('foo', 'bar');

        try {
            $query->get();
            self::fail('Expected a FinderException.');
        } catch (FinderException $exception) {
            self::assertSame(FinderType::Dimensions, $exception->type);
            self::assertCount(1, $exception->messages);
            self::assertSame(FinderMessageType::Error, $exception->messages[0]->type);
            self::assertSame(FinderErrorCode::OptionNotAllowed, $exception->messages[0]->code);
            self::assertSame('Optie foo is niet toegestaan.', $exception->messages[0]->text);
            self::assertSame(['foo'], $exception->messages[0]->parameters);
            self::assertSame('Finder DIM: Optie foo is niet toegestaan. (OptionNotAllowed)', $exception->getMessage());
        }
    }

    public function testThrowsAllErrorMessages(): void
    {
        $query = $this->twinfield(ReplayClient::fixture('finder-error-company-and-office'))->finder(
            FinderType::Dimensions,
        );

        try {
            $query->get();
            self::fail('Expected a FinderException.');
        } catch (FinderException $exception) {
            self::assertCount(2, $exception->messages);
            self::assertSame(
                FinderErrorCode::OptionCompanyAndOptionOfficeCannotBeBothProvided,
                $exception->messages[0]->code,
            );
            self::assertSame([], $exception->messages[0]->parameters);
            self::assertSame(FinderErrorCode::ParameterOutOfRange, $exception->messages[1]->code);
            self::assertSame(['field', '0', '4'], $exception->messages[1]->parameters);
            self::assertStringEndsWith('(and 1 more errors)', $exception->getMessage());
        }
    }

    public function testRejectsInvalidPaging(): void
    {
        $query = $this->twinfield(new ReplayClient())->finder(FinderType::Offices);

        $this->expectException(InvalidArgumentException::class);

        $query->limit(0);
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test/'), $http, $psr17, $psr17);
    }

    private function xpath(RequestInterface $request): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML((string) $request->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        return $xpath;
    }
}
