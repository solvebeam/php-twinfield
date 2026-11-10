<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Budget;

use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Budget\PeriodWeight;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class SpreadingTemplateResourceTest extends TestCase
{
    private const string TEMPLATE_ID = '16fd2706-8baf-433b-82eb-8c7fada847da';

    public function testReadsTheDefaultDistribution(): void
    {
        $http = ReplayClient::jsonFixture('spreading-templates-default-distribution');

        $weights = TestTokens::restClient($http)->spreadingTemplates()->defaultDistribution();

        self::assertCount(12, $weights);
        self::assertEquals(new PeriodWeight(1, 1), $weights[0]);
        self::assertStringEndsWith(
            '/budgeting/spreadingtemplates/defaultdistribution',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testCreatesSearchesUpdatesAndDeletes(): void
    {
        // Follows the Swagger definition: the test companies have no spreading templates.
        $template = (string) json_encode([
            'id' => self::TEMPLATE_ID,
            'lastModifiedAt' => '2026-11-10T10:00:00Z',
            'code' => 'SEASON',
            'name' => 'Season',
            'distribution' => [['period' => 1, 'weight' => 2], ['period' => 2, 'weight' => 1]],
        ]);
        $http = new ReplayClient(
            ReplayClient::raw(
                201,
                '',
                ['Location' => TestTokens::REST_COMPANY_URL . '/budgeting/spreadingtemplates/' . self::TEMPLATE_ID],
            ),
            ReplayClient::raw(200, $template),
            ReplayClient::raw(200, '{"items":[' . $template . '],"total":1,"filteredTotal":1}'),
            ReplayClient::raw(204),
            ReplayClient::raw(200, $template),
            ReplayClient::raw(204),
        );
        $templates = TestTokens::restClient($http)->spreadingTemplates();

        $created = $templates->create('SEASON', 'Season', [new PeriodWeight(1, 2), new PeriodWeight(2, 1)]);
        $found = $templates->search('SEA*')->withDistribution()->get()->items;
        $templates->update($created->id, 'Season (new)', [new PeriodWeight(1, 1)]);
        $templates->delete($created->id);

        self::assertSame('SEASON', $created->code);
        self::assertEquals([new PeriodWeight(1, 2), new PeriodWeight(2, 1)], $created->distribution);
        self::assertEquals([$created], $found);
        self::assertSame(
            '{"code":"SEASON","name":"Season","distribution":[{"period":1,"weight":2},{"period":2,"weight":1}]}',
            (string) $http->requests[0]->getBody(),
        );
        self::assertStringEndsWith(
            '/budgeting/spreadingtemplates?searchPattern=SEA%2A&includeDistributionData=true',
            (string) $http->requests[2]->getUri(),
        );
        self::assertSame(
            '{"name":"Season (new)","distribution":[{"period":1,"weight":1}]}',
            (string) $http->requests[3]->getBody(),
        );
        self::assertSame('DELETE', $http->requests[5]->getMethod());
    }
}
