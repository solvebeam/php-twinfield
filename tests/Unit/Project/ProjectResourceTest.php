<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Project;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Project\ProjectInput;
use SolveBeam\Twinfield\Project\ProjectSummary;
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

final class ProjectResourceTest extends TestCase
{
    public function testSearchesProjects(): void
    {
        $http = ReplayClient::fixture('finder-projects');

        $page = $this->twinfield($http)->projects()->search('P*')
            ->inName()
            ->modifiedSince(new DateTimeImmutable('2025-03-14 09:30:15'))
            ->group('DEV*')
            ->limit(10)
            ->offset(20)
            ->get();

        self::assertSame(2, $page->totalRows);
        self::assertCount(2, $page);
        self::assertEquals([
            new ProjectSummary(TestTokens::company(), 'P0001', 'Website redesign'),
            new ProjectSummary(TestTokens::company(), 'P0002', 'Internal projects & overhead'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertSame('DIM', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('P*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('2', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('21', $xpath->evaluate('string(//tw:Search/tw:firstRow)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame(
            ['dimtype' => 'PRJ', 'modifiedsince' => '20250314093015', 'group' => 'DEV*'],
            $this->finderOptions($xpath),
        );
    }

    public function testSearchesInCodeAndNameByDefault(): void
    {
        $http = ReplayClient::fixture('finder-projects');
        $search = $this->twinfield($http)->projects()->search();

        $search->get();
        self::assertSame('0', $this->soapXpath($http->lastRequest())->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('*', $this->soapXpath($http->lastRequest())->evaluate('string(//tw:Search/tw:pattern)'));

        self::assertSame(1, $search->inCode()->finderQuery()->toSearch()->field);
        self::assertSame(0, $search->inName()->inCodeAndName()->finderQuery()->toSearch()->field);
        self::assertSame('*', $search->finderQuery()->toSearch()->pattern, 'The search is immutable.');
    }

    public function testSearchesAllPagesLazily(): void
    {
        $http = ReplayClient::fixture('finder-projects');

        $codes = [];

        foreach ($this->twinfield($http)->projects()->search()->all() as $index => $project) {
            $codes[$index] = $project->code;
        }

        self::assertSame(['P0001', 'P0002'], $codes);
        self::assertCount(1, $http->requests, 'All rows fit on the first page.');
    }

    public function testCountsWithOneSmallRequest(): void
    {
        $http = ReplayClient::fixture('finder-projects');

        self::assertSame(2, $this->twinfield($http)->projects()->search()->count());
        self::assertSame('1', $this->soapXpath($http->lastRequest())->evaluate('string(//tw:Search/tw:maxRows)'));
    }

    public function testThrowsOnASearchRowWithoutName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Finder project row 0 has no code or name: ["1000"].');

        ProjectSummary::fromFinderRow(['1000'], 0, TestTokens::company());
    }

    public function testReadsAProjectWithAllSettings(): void
    {
        $http = ReplayClient::fixture('project-read');

        $project = $this->twinfield($http)->projects()->get('P0001');

        self::assertSame(
            '<read><type>dimensions</type><office>1000</office><dimtype>PRJ</dimtype><code>P0001</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertEquals(TestTokens::company('1000'), $project->company);
        self::assertSame('P0001', $project->code);
        self::assertSame('6d7bfbf1-58ce-4699-9a78-4fb50bc545be', $project->uid);
        self::assertSame('Website redesign and API documentation for Example Holding', $project->name);
        self::assertSame('Website', $project->shortName);
        self::assertSame(DimensionStatus::Active, $project->status);
        self::assertTrue($project->inUse);
        self::assertSame(DimensionBehaviour::Normal, $project->behaviour);
        self::assertSame(3, $project->touched);
        self::assertEquals(new DateTimeImmutable('2025-03-14 09:30:15'), $project->modified);
        self::assertSame('VH', $project->vatCode);

        $settings = $project->projects;

        self::assertEquals(new DateTimeImmutable('2025-01-01'), $settings->validFrom);
        self::assertEquals(new DateTimeImmutable('2025-12-31'), $settings->validTill);
        self::assertSame('Redesign of the website & the API documentation', $settings->invoiceDescription);
        self::assertEquals(new CodeSetting('SUPER', ChangeMode::Disallow), $settings->authoriser);
        self::assertEquals(new CodeSetting('1010', ChangeMode::Allow), $settings->customer);
        self::assertEquals(new BillableSetting(true, ChangeMode::Allow, forRatio: true), $settings->billable);
        self::assertEquals(new CodeSetting('T001', ChangeMode::Disallow), $settings->rate);
        self::assertEquals([
            new Quantity('KM', 'KM', billable: false, billableLocked: true, mandatory: false),
            new Quantity('Parking', null, billable: true, billableLocked: false, mandatory: true),
        ], $settings->quantities);
    }

    public function testReadsAProjectThatInheritsEverything(): void
    {
        $project = $this->twinfield(ReplayClient::fixture('project-read-inherited'))->projects()->get('P0002');

        self::assertNull($project->shortName);
        self::assertSame(DimensionStatus::Hidden, $project->status);
        self::assertNull($project->vatCode);
        self::assertNull($project->projects->validFrom);
        self::assertNull($project->projects->validTill);
        self::assertNull($project->projects->invoiceDescription);
        self::assertEquals(CodeSetting::inherit(), $project->projects->authoriser);
        self::assertEquals(CodeSetting::inherit(), $project->projects->customer);
        self::assertEquals(BillableSetting::inherit(), $project->projects->billable);
        self::assertEquals(CodeSetting::inherit(), $project->projects->rate);
        self::assertSame([], $project->projects->quantities);
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('project-read-not-found'))->projects()->get('P9999');
            self::fail('Expected a DimensionNotFoundException.');
        } catch (DimensionNotFoundException $exception) {
            self::assertSame('PRJ', $exception->dimensionType);
            self::assertSame('P9999', $exception->dimensionCode);
            self::assertSame('Dimensie P9999 bestaat niet.', $exception->reason);
            self::assertSame('Dimension PRJ P9999 not found: Dimensie P9999 bestaat niet.', $exception->getMessage());
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->twinfield(ReplayClient::fixture('project-read-not-found'))->projects()->find('P9999'));
    }

    public function testFindReturnsNullForACodeOutsideTheCodeFormat(): void
    {
        self::assertNull($this->twinfield(ReplayClient::fixture('project-read-invalid-code'))->projects()->find('NOPE'));
    }

    public function testFindReturnsTheProject(): void
    {
        self::assertSame('P0001', $this->twinfield(ReplayClient::fixture('project-read'))->projects()->find('P0001')?->code);
    }

    public function testThrowsOnUnknownBehaviour(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/behaviour is "magic", expected one of normal, system, template.');

        $this->twinfield(ReplayClient::fixture('project-read-unknown-behaviour'))->projects()->get('P0001');
    }

    public function testThrowsWhenTwinfieldReturnsAnotherDimensionType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/type is "ACT", expected PRJ.');

        $this->twinfield(ReplayClient::fixture('activity-read'))->projects()->get('A001');
    }

    public function testThrowsOnInvalidChangeMode(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/projects/customer has inherit="true" with locked="false"');

        $this->twinfield(ReplayClient::fixture('project-read-invalid-change-mode'))->projects()->get('P0001');
    }

    public function testSavesAProject(): void
    {
        $http = ReplayClient::fixture('project-save');

        $project = $this->twinfield($http)->projects()->save(
            ProjectInput::create('P0100', 'Website redesign')
                ->shortName('Website')
                ->invoiceDescription('Website redesign')
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
            . '<office>1000</office><type>PRJ</type><code>P0100</code>'
            . '<name>Website redesign</name><shortname>Website</shortname>'
            . '<financials><vatcode>VH</vatcode></financials>'
            . '<projects>'
            . '<validfrom>20250101</validfrom><validtill>20251231</validtill>'
            . '<invoicedescription>Website redesign</invoicedescription>'
            . '<authoriser locked="true" inherit="false">SUPER</authoriser>'
            . '<customer locked="false" inherit="false">1010</customer>'
            . '<billable locked="false" inherit="false" forratio="false">true</billable>'
            . '<rate locked="true" inherit="false">T001</rate>'
            . '<quantities><quantity><label>KM</label><rate>KM</rate><billable locked="true">false</billable><mandatory>false</mandatory></quantity></quantities>'
            . '</projects>'
            . '</dimension>',
            $this->xmlRequest($http->lastRequest()),
        );

        // The save response of the documentation has no <modified>.
        self::assertSame('P0100', $project->code);
        self::assertSame('8c1d7a52-3f0e-4b9a-9d55-2e6f1b0c7a11', $project->uid);
        self::assertNull($project->modified);
        self::assertNull($project->vatCode);
        self::assertSame('KM', $project->projects->quantities[0]->label);
    }

    public function testSavesAMinimalProjectWithEverythingInherited(): void
    {
        $http = ReplayClient::fixture('project-save');

        $this->twinfield($http)->projects()->save(ProjectInput::create('P0100', 'R&D'));

        self::assertSame(
            '<dimension>'
            . '<office>1000</office><type>PRJ</type><code>P0100</code><name>R&amp;D</name><shortname/>'
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
            $this->twinfield(ReplayClient::fixture('project-save-error'))->projects()->save(
                ProjectInput::create('P0100', 'Website redesign')->vatCode('XX'),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame('dimension/financials/vatcode: Btw-code XX bestaat niet.', $exception->getMessage());
        }
    }

    public function testCreatesAfterCheckingTheCodeIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('project-read-not-found'),
            ReplayClient::response('project-save'),
        );

        $project = $this->twinfield($http)->projects()->create(ProjectInput::create('P0100', 'Website redesign'));

        self::assertSame('P0100', $project->code);
        self::assertCount(2, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[0]));
        self::assertStringStartsWith('<dimension>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('project-read');

        try {
            $this->twinfield($http)->projects()->create(ProjectInput::create('P0001', 'Website redesign'));
            self::fail('Expected a DimensionAlreadyExistsException.');
        } catch (DimensionAlreadyExistsException $exception) {
            self::assertSame('Dimension PRJ P0001 already exists.', $exception->getMessage());
            self::assertSame('P0001', $exception->dimensionCode);
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testUpdatesAnExistingProject(): void
    {
        $http = new ReplayClient(ReplayClient::response('project-read'), ReplayClient::response('project-save'));
        $projects = $this->twinfield($http)->projects();

        $projects->update(ProjectInput::create('P0001', 'New name'));

        self::assertCount(2, $http->requests);
        self::assertStringContainsString('<name>New name</name>', $this->xmlRequest($http->requests[1]));
    }

    public function testUpdateRefusesAMissingProject(): void
    {
        $http = ReplayClient::fixture('project-read-not-found');

        $this->expectException(DimensionNotFoundException::class);

        try {
            $this->twinfield($http)->projects()->update(ProjectInput::create('P9999', 'New name'));
        } finally {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testEditKeepsAllValues(): void
    {
        $http = new ReplayClient(ReplayClient::response('project-read'), ReplayClient::response('project-save'));
        $projects = $this->twinfield($http)->projects();

        $projects->save($projects->get('P0001')->edit()->name('Renamed'));

        self::assertSame(
            '<dimension>'
            . '<office>1000</office><type>PRJ</type><code>P0001</code>'
            . '<name>Renamed</name><shortname>Website</shortname>'
            . '<financials><vatcode>VH</vatcode></financials>'
            . '<projects>'
            . '<validfrom>20250101</validfrom><validtill>20251231</validtill>'
            . '<invoicedescription>Redesign of the website &amp; the API documentation</invoicedescription>'
            . '<authoriser locked="true" inherit="false">SUPER</authoriser>'
            . '<customer locked="false" inherit="false">1010</customer>'
            . '<billable locked="false" inherit="false" forratio="true">true</billable>'
            . '<rate locked="true" inherit="false">T001</rate>'
            . '<quantities>'
            . '<quantity><label>KM</label><rate>KM</rate><billable locked="true">false</billable><mandatory>false</mandatory></quantity>'
            . '<quantity><label>Parking</label><rate/><billable locked="false">true</billable><mandatory>true</mandatory></quantity>'
            . '</quantities>'
            . '</projects>'
            . '</dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeletes(): void
    {
        $http = ReplayClient::fixture('project-delete');

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->projects()->delete('P0100'));
        self::assertSame(
            '<dimension status="deleted"><office>1000</office><type>PRJ</type><code>P0100</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesAProjectInUse(): void
    {
        self::assertSame(
            DeleteResult::Hidden,
            $this->twinfield(ReplayClient::fixture('project-delete-hidden'))->projects()->delete('P0001'),
        );
    }

    public function testDeleteThrowsNotFound(): void
    {
        $this->expectException(DimensionNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('project-read-not-found'))->projects()->delete('P9999');
    }

    public function testDeleteThrowsWhenTheProjectStaysActive(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Project P0001 is still active after deleting it.');

        $this->twinfield(ReplayClient::fixture('project-activate'))->projects()->delete('P0001');
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('project-activate');

        $this->twinfield($http)->projects()->activate('P0001');

        self::assertSame(
            '<dimension status="active"><office>1000</office><type>PRJ</type><code>P0001</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testRequiresACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Projects belong to a company: use company() first.');

        $twinfield->projects()->get('P0001');
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
