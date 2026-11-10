<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Activity;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Activity\ActivityInput;
use SolveBeam\Twinfield\Activity\ActivitySummary;
use SolveBeam\Twinfield\Dimension\BillableSetting;
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\CodeSetting;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Dimension\Quantity;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class ActivityResourceTest extends TestCase
{
    public function testSearchesActivities(): void
    {
        $http = ReplayClient::fixture('finder-activities');

        $page = $this->twinfield($http)->activities()->search('A*')
            ->inName()
            ->modifiedSince(new DateTimeImmutable('2025-03-14 09:30:15'))
            ->group('DEV*')
            ->limit(10)
            ->offset(20)
            ->get();

        self::assertSame(2, $page->totalRows);
        self::assertCount(2, $page);
        self::assertEquals([
            new ActivitySummary(TestTokens::company(), 'A001', 'Deploy API documentation'),
            new ActivitySummary(TestTokens::company(), 'A002', 'Consultancy & advice'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertSame('DIM', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('A*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('2', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('21', $xpath->evaluate('string(//tw:Search/tw:firstRow)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame(
            ['dimtype' => 'ACT', 'modifiedsince' => '20250314093015', 'group' => 'DEV*'],
            $this->finderOptions($xpath),
        );
    }

    public function testSearchesInCodeAndNameByDefault(): void
    {
        $http = ReplayClient::fixture('finder-activities');
        $search = $this->twinfield($http)->activities()->search();

        $search->get();
        self::assertSame('0', $this->soapXpath($http->lastRequest())->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('*', $this->soapXpath($http->lastRequest())->evaluate('string(//tw:Search/tw:pattern)'));

        self::assertSame(1, $search->inCode()->finderQuery()->toSearch()->field);
        self::assertSame(0, $search->inName()->inCodeAndName()->finderQuery()->toSearch()->field);
        self::assertSame('*', $search->finderQuery()->toSearch()->pattern, 'The search is immutable.');
    }

    public function testSearchesAllPagesLazily(): void
    {
        $http = ReplayClient::fixture('finder-activities');

        $codes = [];

        foreach ($this->twinfield($http)->activities()->search()->all() as $index => $activity) {
            $codes[$index] = $activity->code;
        }

        self::assertSame(['A001', 'A002'], $codes);
        self::assertCount(1, $http->requests, 'All rows fit on the first page.');
    }

    public function testCountsWithOneSmallRequest(): void
    {
        $http = ReplayClient::fixture('finder-activities');

        self::assertSame(2, $this->twinfield($http)->activities()->search()->count());
        self::assertSame('1', $this->soapXpath($http->lastRequest())->evaluate('string(//tw:Search/tw:maxRows)'));
    }

    public function testThrowsOnASearchRowWithoutName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Finder activity row 0 has no code or name: ["1000"].');

        ActivitySummary::fromFinderRow(['1000'], 0, TestTokens::company());
    }

    public function testReadsAnActivityWithAllSettings(): void
    {
        $http = ReplayClient::fixture('activity-read');

        $activity = $this->twinfield($http)->activities()->get('A001');

        self::assertSame(
            '<read><type>dimensions</type><office>1000</office><dimtype>ACT</dimtype><code>A001</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertEquals(TestTokens::company('1000'), $activity->company);
        self::assertSame('A001', $activity->code);
        self::assertSame('6d7bfbf1-58ce-4699-9a78-4fb50bc545be', $activity->uid);
        self::assertSame('Deploy API documentation', $activity->name);
        self::assertSame('API docs', $activity->shortName);
        self::assertSame(DimensionStatus::Active, $activity->status);
        self::assertTrue($activity->inUse);
        self::assertSame(DimensionBehaviour::Normal, $activity->behaviour);
        self::assertSame(3, $activity->touched);
        self::assertEquals(new DateTimeImmutable('2025-03-14 09:30:15'), $activity->modified);
        self::assertSame('VH', $activity->vatCode);

        $projects = $activity->projects;

        self::assertEquals(new DateTimeImmutable('2025-01-01'), $projects->validFrom);
        self::assertEquals(new DateTimeImmutable('2025-12-31'), $projects->validTill);
        self::assertSame('Deploy the API documentation & examples', $projects->invoiceDescription);
        self::assertEquals(new CodeSetting('SUPER', ChangeMode::Disallow), $projects->authoriser);
        self::assertEquals(new CodeSetting('1010', ChangeMode::Allow), $projects->customer);
        self::assertEquals(new BillableSetting(true, ChangeMode::Allow, forRatio: true), $projects->billable);
        self::assertEquals(new CodeSetting('T001', ChangeMode::Disallow), $projects->rate);
        self::assertEquals([
            new Quantity('KM', 'KM', billable: false, billableLocked: true, mandatory: false),
            new Quantity('Parking', null, billable: true, billableLocked: false, mandatory: true),
        ], $projects->quantities);
    }

    public function testReadsAnActivityThatInheritsEverything(): void
    {
        $activity = $this->twinfield(ReplayClient::fixture('activity-read-inherited'))->activities()->get('A002');

        self::assertNull($activity->shortName);
        self::assertSame(DimensionStatus::Hidden, $activity->status);
        self::assertNull($activity->vatCode);
        self::assertNull($activity->projects->validFrom);
        self::assertNull($activity->projects->validTill);
        self::assertNull($activity->projects->invoiceDescription);
        self::assertEquals(CodeSetting::inherit(), $activity->projects->authoriser);
        self::assertEquals(CodeSetting::inherit(), $activity->projects->customer);
        self::assertEquals(BillableSetting::inherit(), $activity->projects->billable);
        self::assertEquals(CodeSetting::inherit(), $activity->projects->rate);
        self::assertSame([], $activity->projects->quantities);
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('activity-read-not-found'))->activities()->get('A999');
            self::fail('Expected a DimensionNotFoundException.');
        } catch (DimensionNotFoundException $exception) {
            self::assertSame('ACT', $exception->dimensionType);
            self::assertSame('A999', $exception->dimensionCode);
            self::assertSame('Dimensie A999 bestaat niet.', $exception->reason);
            self::assertSame('Dimension ACT A999 not found: Dimensie A999 bestaat niet.', $exception->getMessage());
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        self::assertNull(
            $this->twinfield(ReplayClient::fixture('activity-read-not-found'))->activities()->find('A999'),
        );
    }

    public function testFindReturnsNullForACodeOutsideTheCodeFormat(): void
    {
        self::assertNull(
            $this->twinfield(ReplayClient::fixture('activity-read-invalid-code'))->activities()->find('NOPE'),
        );
    }

    public function testFindReturnsTheActivity(): void
    {
        self::assertSame(
            'A001',
            $this->twinfield(ReplayClient::fixture('activity-read'))->activities()->find('A001')?->code,
        );
    }

    public function testThrowsOnUnknownBehaviour(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/behaviour is "magic", expected one of normal, system, template.');

        $this->twinfield(ReplayClient::fixture('activity-read-unknown-behaviour'))->activities()->get('A001');
    }

    public function testThrowsOnInvalidChangeMode(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/projects/customer has inherit="true" with locked="false"');

        $this->twinfield(ReplayClient::fixture('activity-read-invalid-change-mode'))->activities()->get('A001');
    }

    public function testSavesAnActivity(): void
    {
        $http = ReplayClient::fixture('activity-save');

        $activity = $this->twinfield($http)->activities()->save(
            ActivityInput::create('A100', 'Deploy API documentation')
                ->shortName('API docs')
                ->invoiceDescription('Deploy API documentation')
                ->validFrom(new DateTimeImmutable('2025-01-01 13:45'))
                ->validTill(new DateTimeImmutable('2025-12-31'))
                ->authoriser('SUPER', ChangeMode::Disallow)
                ->customer('1010')
                ->billable(true)
                ->rate('T001', ChangeMode::Disallow)
                ->vatCode('VH')
                ->quantity(Quantity::create('KM')->rate('KM')->billable(false, locked: true)),
        );

        self::assertSame(
            '<dimension>'
            . '<office>1000</office><type>ACT</type><code>A100</code>'
            . '<name>Deploy API documentation</name><shortname>API docs</shortname>'
            . '<financials><vatcode>VH</vatcode></financials>'
            . '<projects>'
            . '<validfrom>20250101</validfrom><validtill>20251231</validtill>'
            . '<invoicedescription>Deploy API documentation</invoicedescription>'
            . '<authoriser locked="true" inherit="false">SUPER</authoriser>'
            . '<customer locked="false" inherit="false">1010</customer>'
            . '<billable locked="false" inherit="false" forratio="false">true</billable>'
            . '<rate locked="true" inherit="false">T001</rate>'
            . '<quantities><quantity><label>KM</label><rate>KM</rate>'
            . '<billable locked="true">false</billable><mandatory>false</mandatory></quantity></quantities>'
            . '</projects>'
            . '</dimension>',
            $this->xmlRequest($http->lastRequest()),
        );

        // The save response of the documentation has no <modified>.
        self::assertSame('A100', $activity->code);
        self::assertSame('8c1d7a52-3f0e-4b9a-9d55-2e6f1b0c7a11', $activity->uid);
        self::assertNull($activity->modified);
        self::assertNull($activity->vatCode);
        self::assertSame('KM', $activity->projects->quantities[0]->label);
    }

    public function testSavesAMinimalActivityWithEverythingInherited(): void
    {
        $http = ReplayClient::fixture('activity-save');

        $this->twinfield($http)->activities()->save(ActivityInput::create('A100', 'R&D'));

        self::assertSame(
            '<dimension>'
            . '<office>1000</office><type>ACT</type><code>A100</code><name>R&amp;D</name><shortname/>'
            . '<financials><vatcode/></financials>'
            . '<projects><validfrom/><validtill/><invoicedescription/>'
            . '<authoriser locked="true" inherit="true"/>'
            . '<customer locked="true" inherit="true"/>'
            . '<billable locked="true" inherit="true" forratio="false"/>'
            . '<rate locked="true" inherit="true"/>'
            . '<quantities/></projects>'
            . '</dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testThrowsTheTwinfieldErrorsWhenSavingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('activity-save-error'))->activities()->save(
                ActivityInput::create('A100', 'Deploy API documentation')->vatCode('XX'),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame('dimension/financials/vatcode: Btw-code XX bestaat niet.', $exception->getMessage());
        }
    }

    public function testCreatesAfterCheckingTheCodeIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('activity-read-not-found'),
            ReplayClient::response('activity-save'),
        );

        $activity = $this->twinfield($http)->activities()->create(
            ActivityInput::create('A100', 'Deploy API documentation'),
        );

        self::assertSame('A100', $activity->code);
        self::assertCount(2, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[0]));
        self::assertStringStartsWith('<dimension>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('activity-read');

        try {
            $this->twinfield($http)->activities()->create(ActivityInput::create('A001', 'Deploy API documentation'));
            self::fail('Expected a DimensionAlreadyExistsException.');
        } catch (DimensionAlreadyExistsException $exception) {
            self::assertSame('Dimension ACT A001 already exists.', $exception->getMessage());
            self::assertSame('A001', $exception->dimensionCode);
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testUpdatesAnExistingActivity(): void
    {
        $http = new ReplayClient(ReplayClient::response('activity-read'), ReplayClient::response('activity-save'));
        $activities = $this->twinfield($http)->activities();

        $activities->update(ActivityInput::create('A001', 'New name'));

        self::assertCount(2, $http->requests);
        self::assertStringContainsString('<name>New name</name>', $this->xmlRequest($http->requests[1]));
    }

    public function testUpdateRefusesAMissingActivity(): void
    {
        $http = ReplayClient::fixture('activity-read-not-found');

        $this->expectException(DimensionNotFoundException::class);

        try {
            $this->twinfield($http)->activities()->update(ActivityInput::create('A999', 'New name'));
        } finally {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testEditKeepsAllValues(): void
    {
        $http = new ReplayClient(ReplayClient::response('activity-read'), ReplayClient::response('activity-save'));
        $activities = $this->twinfield($http)->activities();

        $activities->save($activities->get('A001')->edit()->name('Renamed'));

        self::assertSame(
            '<dimension>'
            . '<office>1000</office><type>ACT</type><code>A001</code>'
            . '<name>Renamed</name><shortname>API docs</shortname>'
            . '<financials><vatcode>VH</vatcode></financials>'
            . '<projects>'
            . '<validfrom>20250101</validfrom><validtill>20251231</validtill>'
            . '<invoicedescription>Deploy the API documentation &amp; examples</invoicedescription>'
            . '<authoriser locked="true" inherit="false">SUPER</authoriser>'
            . '<customer locked="false" inherit="false">1010</customer>'
            . '<billable locked="false" inherit="false" forratio="true">true</billable>'
            . '<rate locked="true" inherit="false">T001</rate>'
            . '<quantities>'
            . '<quantity><label>KM</label><rate>KM</rate>'
            . '<billable locked="true">false</billable><mandatory>false</mandatory></quantity>'
            . '<quantity><label>Parking</label><rate/>'
            . '<billable locked="false">true</billable><mandatory>true</mandatory></quantity>'
            . '</quantities>'
            . '</projects>'
            . '</dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeletes(): void
    {
        $http = ReplayClient::fixture('activity-delete');

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->activities()->delete('A100'));
        self::assertSame(
            '<dimension status="deleted"><office>1000</office><type>ACT</type><code>A100</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesAnActivityInUse(): void
    {
        self::assertSame(
            DeleteResult::Hidden,
            $this->twinfield(ReplayClient::fixture('activity-delete-hidden'))->activities()->delete('A001'),
        );
    }

    public function testDeleteThrowsNotFound(): void
    {
        $this->expectException(DimensionNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('activity-read-not-found'))->activities()->delete('A999');
    }

    public function testDeleteThrowsWhenTheActivityStaysActive(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Activity A001 is still active after deleting it.');

        $this->twinfield(ReplayClient::fixture('activity-activate'))->activities()->delete('A001');
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('activity-activate');

        $this->twinfield($http)->activities()->activate('A001');

        self::assertSame(
            '<dimension status="active"><office>1000</office><type>ACT</type><code>A001</code></dimension>',
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
        $this->expectExceptionMessage('Activities belong to a company: use company() first.');

        $twinfield->activities()->get('A001');
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
