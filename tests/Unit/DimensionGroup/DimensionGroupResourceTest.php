<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\DimensionGroup;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupAlreadyExistsException;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupDimension;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupInput;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupNotFoundException;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupStatus;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupSummary;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class DimensionGroupResourceTest extends TestCase
{
    public function testSearchesDimensionGroups(): void
    {
        $http = ReplayClient::fixture('finder-dimension-groups');

        $page = $this->twinfield($http)->dimensionGroups()->search('*customers*')
            ->inName()
            ->dimensionType('DEB')
            ->limit(10)
            ->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new DimensionGroupSummary(TestTokens::company(), 'KEY-ACCOUNTS', 'Key accounts'),
            new DimensionGroupSummary(TestTokens::company(), 'NL-CUSTOMERS', 'Dutch customers & partners'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('GRP', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('*customers*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('2', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame(['dimtype' => 'DEB'], $this->finderOptions($xpath), 'The company comes from the header.');
    }

    public function testSearchesAllPagesLazily(): void
    {
        $http = ReplayClient::fixture('finder-dimension-groups');

        $codes = array_map(
            static fn (DimensionGroupSummary $group): string => $group->code,
            iterator_to_array($this->twinfield($http)->dimensionGroups()->search()->all(), false),
        );

        self::assertSame(['KEY-ACCOUNTS', 'NL-CUSTOMERS'], $codes);
        self::assertSame([], $this->finderOptions($this->soapXpath($http->lastRequest())));
    }

    public function testThrowsOnASearchRowWithoutName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Finder dimension group row 3 has no code or name: ["NL",""].');

        DimensionGroupSummary::fromFinderRow(['NL', ''], 3, TestTokens::company());
    }

    public function testReadsADimensionGroupAfterCheckingTheFinder(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('dimension-group-read'),
        );

        $group = $this->twinfield($http)->dimensionGroups()->get('NL-CUSTOMERS');

        self::assertCount(2, $http->requests);

        $finder = $this->soapXpath($http->requests[0]);
        self::assertSame('GRP', $finder->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('NL-CUSTOMERS', $finder->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $finder->evaluate('string(//tw:Search/tw:field)'), 'Searches the code only.');

        self::assertSame(
            '<dimensiongroup><office>1000</office><code>NL-CUSTOMERS</code></dimensiongroup>',
            $this->xmlRequest($http->requests[1]),
        );

        self::assertEquals(TestTokens::company('1000'), $group->company);
        self::assertSame('NL-CUSTOMERS', $group->code);
        self::assertSame('Dutch customers & partners', $group->name);
        self::assertSame('NL customers', $group->shortName);
        self::assertSame(DimensionGroupStatus::Active, $group->status);
        self::assertSame(4, $group->touched);
        self::assertEquals([
            new DimensionGroupDimension('DEB', '1000', 'Example customer'),
            new DimensionGroupDimension('DEB', '1001', 'Another customer'),
            new DimensionGroupDimension('KPL', '100', 'Sales & marketing'),
        ], $group->dimensions);
    }

    public function testReadsAGroupWithoutDimensionsAndShortName(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('dimension-group-read-empty'),
        );

        $group = $this->twinfield($http)->dimensionGroups()->get('NL-CUSTOMERS');

        self::assertNull($group->shortName);
        self::assertSame([], $group->dimensions);
        self::assertSame([], $group->types());
    }

    public function testDoesNotReadACodeThatIsNotInTheFinder(): void
    {
        // NL-CUSTOMERS-2 matches the search on NL-CUSTOMERS, but is not the same code.
        $http = ReplayClient::fixture('finder-dimension-groups-nl-2');

        try {
            $this->twinfield($http)->dimensionGroups()->get('NL-CUSTOMERS');
            self::fail('Expected DimensionGroupNotFoundException.');
        } catch (DimensionGroupNotFoundException $exception) {
            self::assertSame('NL-CUSTOMERS', $exception->dimensionGroupCode);
            self::assertSame('Dimension group NL-CUSTOMERS not found.', $exception->getMessage());
        }

        self::assertCount(1, $http->requests, 'No <dimensiongroup> request, it would create the group.');
    }

    public function testFindReturnsNullForAnUnknownCode(): void
    {
        $http = ReplayClient::fixture('finder-dimension-groups-nl-2');

        self::assertNull($this->twinfield($http)->dimensionGroups()->find('NL-CUSTOMERS'));
    }

    public function testExists(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('finder-dimension-groups-nl-2'),
        );
        $groups = $this->twinfield($http)->dimensionGroups();

        self::assertTrue($groups->exists('NL-CUSTOMERS'));
        self::assertFalse($groups->exists('NL-CUSTOMERS'));
    }

    public function testThrowsOnAnUnknownStatus(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('dimension-group-read-unknown-status'),
        );

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimensiongroup/@status is "hide", expected one of active, deleted.');

        $this->twinfield($http)->dimensionGroups()->get('NL-CUSTOMERS');
    }

    public function testThrowsOnADuplicateDimension(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('dimension-group-read-duplicate-dimension'),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dimension group NL-CUSTOMERS has DEB 1000 more than once.');

        $this->twinfield($http)->dimensionGroups()->get('NL-CUSTOMERS');
    }

    public function testCreatesADimensionGroup(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('dimension-group-save'),
        );

        $group = $this->twinfield($http)->dimensionGroups()->create(
            DimensionGroupInput::create('KEY-ACCOUNTS', 'Key accounts')->shortName('Key')->add('DEB', '1000', '1001'),
        );

        self::assertCount(2, $http->requests, 'The save response is the group: no read back.');
        $finder = $this->soapXpath($http->requests[0]);
        self::assertSame('KEY-ACCOUNTS', $finder->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame(
            '<dimensiongroup status="active"><office>1000</office><code>KEY-ACCOUNTS</code><name>Key accounts</name>'
            . '<shortname>Key</shortname><dimensions>'
            . '<dimension><type>DEB</type><code>1000</code></dimension>'
            . '<dimension><type>DEB</type><code>1001</code></dimension>'
            . '</dimensions></dimensiongroup>',
            $this->xmlRequest($http->requests[1]),
        );
        self::assertSame('KEY-ACCOUNTS', $group->code);
        self::assertSame('Key', $group->shortName);
        self::assertSame(1, $group->touched);
        self::assertSame(['1000', '1001'], $group->codes('DEB'));
    }

    public function testDoesNotCreateAnExistingGroup(): void
    {
        $http = ReplayClient::fixture('finder-dimension-groups-nl');

        $this->expectException(DimensionGroupAlreadyExistsException::class);
        $this->expectExceptionMessage('Dimension group NL-CUSTOMERS already exists.');

        $input = DimensionGroupInput::create('NL-CUSTOMERS', 'Dutch customers');

        $this->twinfield($http)->dimensionGroups()->create($input);
    }

    public function testUpdatesAGroupWithAllItsDimensions(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('dimension-group-read'),
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('dimension-group-read'),
        );
        $groups = $this->twinfield($http)->dimensionGroups();

        $group = $groups->get('NL-CUSTOMERS');
        $groups->update($group->edit()->add('DEB', '1002')->remove('DEB', '1000')->shortName(null));

        self::assertSame(
            '<dimensiongroup status="active"><office>1000</office><code>NL-CUSTOMERS</code>'
            . '<name>Dutch customers &amp; partners</name><shortname/><dimensions>'
            . '<dimension><type>DEB</type><code>1001</code></dimension>'
            . '<dimension><type>KPL</type><code>100</code></dimension>'
            . '<dimension><type>DEB</type><code>1002</code></dimension>'
            . '</dimensions></dimensiongroup>',
            $this->xmlRequest($http->requests[3]),
        );
    }

    public function testDoesNotUpdateAnUnknownGroup(): void
    {
        $http = ReplayClient::fixture('finder-dimension-groups-nl-2');

        $this->expectException(DimensionGroupNotFoundException::class);

        $input = DimensionGroupInput::create('NL-CUSTOMERS', 'Dutch customers');

        $this->twinfield($http)->dimensionGroups()->update($input);
    }

    public function testSaveThrowsTwinfieldRefusals(): void
    {
        $http = ReplayClient::fixture('dimension-group-save-error');

        try {
            $this->twinfield($http)->dimensionGroups()->save(
                DimensionGroupInput::create('KEY-ACCOUNTS', 'Key accounts')->add('DEB', '9999'),
            );
            self::fail('Expected ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame('dimensiongroup/dimensions/dimension/code', $exception->result->errors()[0]->path);
            self::assertSame('Dimensie 9999 bestaat niet.', $exception->result->errors()[0]->text);
        }

        self::assertCount(1, $http->requests, 'save() does not check the Finder.');
    }

    public function testDeletesAGroupAfterEmptyingIt(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('dimension-group-empty'),
            ReplayClient::response('dimension-group-delete'),
        );

        $this->twinfield($http)->dimensionGroups()->delete('NL-CUSTOMERS');

        self::assertCount(3, $http->requests);
        self::assertSame(
            '<dimensiongroup><office>1000</office><code>NL-CUSTOMERS</code><dimensions/></dimensiongroup>',
            $this->xmlRequest($http->requests[1]),
            'Empties the group first, so no dimension keeps the code of the deleted group.',
        );
        self::assertSame(
            '<dimensiongroup status="deleted"><office>1000</office><code>NL-CUSTOMERS</code></dimensiongroup>',
            $this->xmlRequest($http->requests[2]),
        );
    }

    public function testDoesNotDeleteAGroupThatStillHasDimensions(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('dimension-group-read'),
        );

        try {
            $this->twinfield($http)->dimensionGroups()->delete('NL-CUSTOMERS');
            self::fail('Expected UnexpectedResponseException.');
        } catch (UnexpectedResponseException $exception) {
            self::assertSame(
                'Dimension group NL-CUSTOMERS still has dimensions after emptying it.',
                $exception->getMessage(),
            );
            self::assertCount(2, $http->requests, 'No delete request.');
        }
    }

    public function testDoesNotDeleteAnUnknownGroup(): void
    {
        $http = ReplayClient::fixture('finder-dimension-groups-nl-2');

        try {
            $this->twinfield($http)->dimensionGroups()->delete('NL-CUSTOMERS');
            self::fail('Expected DimensionGroupNotFoundException.');
        } catch (DimensionGroupNotFoundException) {
            self::assertCount(1, $http->requests, 'No delete request, it would create the group.');
        }
    }

    public function testThrowsWhenAGroupIsStillActiveAfterDeleting(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('finder-dimension-groups-nl'),
            ReplayClient::response('dimension-group-empty'),
            ReplayClient::response('dimension-group-delete-still-active'),
        );

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Dimension group NL-CUSTOMERS is still active after deleting it.');

        $this->twinfield($http)->dimensionGroups()->delete('NL-CUSTOMERS');
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $http = new ReplayClient();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Dimension groups belong to a company: use company() first.');

        $twinfield->dimensionGroups()->save(DimensionGroupInput::create('NL-CUSTOMERS', 'Dutch customers'));
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
