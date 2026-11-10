<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\FixedAsset;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\FixedAsset\ClassActivationStatus;
use SolveBeam\Twinfield\FixedAsset\DepreciationMethod;
use SolveBeam\Twinfield\FixedAsset\FixedAssetActionType;
use SolveBeam\Twinfield\FixedAsset\FixedAssetStatus;
use SolveBeam\Twinfield\FixedAsset\LifetimeType;
use SolveBeam\Twinfield\FixedAsset\ReconciliationReportRequest;
use SolveBeam\Twinfield\FixedAsset\Regime;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class FixedAssetResourceTest extends TestCase
{
    private const ASSET_ID = 'e14c0b05-3f46-de68-d605-f12a857fd49d';

    public function testSearchesFixedAssetsWithAllFields(): void
    {
        $http = ReplayClient::jsonFixture('fixed-assets-list');

        $page = TestTokens::restClient($http)->fixedAssets()->search('2*')
            ->status(FixedAssetStatus::Active)
            ->regime(Regime::Fiscal)
            ->asOf(Period::of(2026, 3))
            ->investedBetween(new DateTimeImmutable('2020-01-01'), null)
            ->amountBetween('1000', null)
            ->limit(2)
            ->get();

        $asset = $page->items[0];

        self::assertSame(self::ASSET_ID, $asset->id);
        self::assertSame('2', $asset->code);
        self::assertSame(FixedAssetStatus::Active, $asset->status);
        self::assertNotNull($asset->postingErrorMessage);
        self::assertEquals(Period::of(2021, 12), $asset->lastDepreciatedPeriod);
        self::assertEquals(new DateTimeImmutable('2020-03-25'), $asset->investmentDate);
        self::assertNull($asset->disposeDate);
        self::assertSame('0.00', (string) $asset->disposePrice);
        self::assertSame('144101.00', (string) $asset->netBookValue);
        self::assertSame('955130.00', (string) $asset->purchaseValue);
        self::assertNull($asset->groupId);
        self::assertSame(11, $asset->version);
        self::assertSame(
            TestTokens::REST_COMPANY_URL . '/fixedassets/assets?fields=%2A&searchPattern=2%2A&status=active&regime=fiscal'
            . '&timeRelatedFiltersYear=2026&timeRelatedFiltersPeriodNumber=3&investmentFrom=2020-01-01&amountFrom=1000.00&limit=2',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testGetsAFixedAsset(): void
    {
        $http = ReplayClient::jsonFixture('fixed-assets-get');

        $asset = TestTokens::restClient($http)->fixedAssets()->get(self::ASSET_ID);

        self::assertSame('2', $asset->code);
        self::assertSame('EUR', $asset->currency);
        self::assertNull($asset->supplierId);
        self::assertSame([], $asset->transactionIds);
        self::assertEquals(Period::of(2022, 1), $asset->nextPeriodToDepreciate);
        self::assertCount(3, $asset->depreciationSettings);

        $fiscal = $asset->depreciation(Regime::Fiscal);

        self::assertNotNull($fiscal);
        self::assertEquals(Period::of(2021, 4), $fiscal->startPeriod);
        self::assertSame('90207.00', (string) $fiscal->remainderValue);
        self::assertFalse($fiscal->isRemainderValueImplicit);
        self::assertSame('0.00', (string) $fiscal->stopValue);
        self::assertSame(DepreciationMethod::StraightLine, $fiscal->methods[0]->method);
        self::assertSame(LifetimeType::Linear, $fiscal->methods[0]->lifetimeType);
        self::assertSame('250', $fiscal->methods[0]->lifetime);
        self::assertNull($asset->depreciation(Regime::Generic));

        self::assertSame([], $asset->freeTexts);
        self::assertNull($asset->freeText(1));
        self::assertNull($asset->licensePlate);
        self::assertSame([], $asset->locations);
        self::assertSame(2021, $asset->assetTypes[0]->year);
        self::assertNull($asset->assetTypes[0]->type2Id);
        self::assertSame(['fiscal' => 10, 'commercial' => 10, 'economic' => 10], $asset->currentPeriodNumbers);
        self::assertSame('40872dec-7eec-9c33-3fc0-66bf17f5e736', $asset->classes[0]->id);
        self::assertSame(2021, $asset->classes[0]->year);
        self::assertEquals(Period::of(2021, 12), $asset->costCenters[0]->from);
        self::assertTrue($asset->isImportedWithMimicHistory);
        self::assertEquals(Period::of(2021, 12), $asset->lastMimicPeriod);
        self::assertSame(Regime::Generic, $asset->purchaseValues[0]->regime);
        self::assertSame('144101.00', (string) $asset->netBookValue);
        self::assertSame(
            TestTokens::REST_COMPANY_URL . '/fixedassets/assets/' . self::ASSET_ID . '?fields=%2A',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testGetsADisposedFixedAsset(): void
    {
        $asset = TestTokens::restClient(ReplayClient::jsonFixture('fixed-assets-get-disposed'))->fixedAssets()
            ->get('cb7bf956-0854-46d3-aa20-7dd6bd38aad8');

        self::assertSame(FixedAssetStatus::Disposed, $asset->status);
        self::assertSame('1017.20', (string) $asset->netBookValue);
        self::assertSame([], $asset->locations, 'Twinfield sends null instead of an empty list.');
        self::assertNull($asset->lastRevaluationPeriod);
    }

    public function testReadsTheHistoryOfAFixedAsset(): void
    {
        $http = ReplayClient::jsonFixture('fixed-assets-actions');

        $actions = TestTokens::restClient($http)->fixedAssets()->actions(self::ASSET_ID);

        self::assertSame(FixedAssetActionType::Imported, $actions[0]->type);
        self::assertSame(FixedAssetActionType::HistoricalValuesAdded, $actions[2]->type);
        self::assertSame('ACTIVA', $actions[2]->transactions[0]->dayBook);
        self::assertSame(209806460, $actions[2]->transactions[0]->number);
        self::assertSame('NCEPA', $actions[2]->user);
        self::assertSame('2022-04-15T10:13:22+00:00', $actions[2]->timestamp->format(DATE_ATOM));
        self::assertStringEndsWith('/fixedassets/assets/' . self::ASSET_ID . '/actions', (string) $http->lastRequest()->getUri());
    }

    public function testListsAndGetsClasses(): void
    {
        $http = new ReplayClient(
            ReplayClient::jsonResponse('fixed-assets-classes-list'),
            ReplayClient::jsonResponse('fixed-assets-classes-get'),
        );
        $fixedAssets = TestTokens::restClient($http)->fixedAssets();

        $summary = $fixedAssets->classes()->get()->items[0];
        $class = $fixedAssets->getClass($summary->id);

        self::assertSame('Oqrrhxky', $summary->name);
        self::assertSame('130', $summary->lifetime);
        self::assertTrue($summary->used);
        self::assertSame('SLINVFWP', $class->code);
        self::assertSame(ClassActivationStatus::Active, $class->activationStatus);
        self::assertSame(Regime::Fiscal, $class->depreciationSettings[0]->regime);
        self::assertSame('0c45b9d2-7336-ea7f-0d85-59d93c7d4ecd', $class->purchaseValueAccountId);
        self::assertStringEndsWith('/fixedassets/classes?fields=%2A', (string) $http->requests[0]->getUri());
        self::assertStringEndsWith('/fixedassets/classes/' . $summary->id . '?fields=%2A', (string) $http->requests[1]->getUri());
    }

    public function testGetsAGroupWithStopValues(): void
    {
        // Follows the Swagger definition: the test companies have no groups.
        $group = TestTokens::restClient(ReplayClient::jsonFixture('fixed-assets-groups-get'))->fixedAssets()
            ->getGroup('16fd2706-8baf-433b-82eb-8c7fada847da');

        self::assertSame('Vehicles', $group->name);
        self::assertSame(2025, $group->startYear);
        self::assertSame('1000.00', (string) $group->stopValues[2026]['fiscal']);
        self::assertSame('500.00', (string) $group->stopValues[2026]['commercial']);
    }

    public function testQueuesAndDownloadsAReconciliationReport(): void
    {
        // Follows the Swagger definition: live, the test user gets HTTP 403 when queueing a report.
        $location = TestTokens::REST_COMPANY_URL . '/fixedassets/backgroundreports/42/status';
        $http = new ReplayClient(
            ReplayClient::raw(202, '', ['Location' => $location]),
            ReplayClient::raw(200),
            ReplayClient::raw(303, '', ['Location' => 'download']),
            ReplayClient::raw(200, '{"lines":[]}', ['Content-Type' => 'application/json']),
        );
        $fixedAssets = TestTokens::restClient($http)->fixedAssets();

        $id = $fixedAssets->queueReconciliationReport(new ReconciliationReportRequest(
            Period::of(2026, 12),
            Regime::Fiscal,
            statusFrom: FixedAssetStatus::Active,
            classCodeFrom: 'GEBOUWEN',
        ));

        self::assertSame('42', $id);
        self::assertFalse($fixedAssets->isReconciliationReportReady($id));
        self::assertTrue($fixedAssets->isReconciliationReportReady($id));
        self::assertSame('{"lines":[]}', $fixedAssets->downloadReconciliationReport($id)->contents);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertStringEndsWith(
            '/fixedassets/backgroundreports/reconciliation?year=2026&periodNumber=12&regime=fiscal&assetStatusFrom=active&classCodeFrom=GEBOUWEN',
            (string) $http->requests[0]->getUri(),
        );
        self::assertStringEndsWith('/fixedassets/backgroundreports/42/download', (string) $http->requests[3]->getUri());
    }

    public function testThrowsWhenAQueuedReportHasNoId(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        TestTokens::restClient(new ReplayClient(ReplayClient::raw(202)))->fixedAssets()
            ->queueReconciliationReport(new ReconciliationReportRequest(Period::of(2026, 12), Regime::Fiscal));
    }
}
