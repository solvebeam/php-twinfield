<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Rest\RestFile;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Fixed assets of the new fixed assets module (REST API): assets with their depreciation per regime and
 * history, classes, groups and the reconciliation report. Read-only; scope `readFixedAssets`.
 *
 *   $fixedAssets = $twinfield->company('1000')->fixedAssets();
 *
 *   foreach ($fixedAssets->search()->status(FixedAssetStatus::Active)->all() as $summary) {
 *       echo $summary->code, ' ', $summary->description, ' ', $summary->netBookValue, PHP_EOL;
 *   }
 *
 *   $asset = $fixedAssets->get($summary->id);
 *   $history = $fixedAssets->actions($asset->id);
 *
 * Twinfield has two fixed assets modules that share no data. This is the new one; the classic module
 * (dimension type `AST`, XML web services) is `classicFixedAssets()`.
 *
 * @see https://api.accounting.twinfield.com/Api/swagger/ui/index#!/Fixed32Assets32Assets
 */
final readonly class FixedAssetResource
{
    private const string PATH = '/organisations/{organisationId}/companies/{companyId}/fixedassets';

    /** Without `fields`, the REST API returns only the default fields (id, code, …). */
    private const array ALL_FIELDS = ['fields' => '*'];

    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    public function search(string $pattern = '*'): FixedAssetSearch
    {
        $search = new FixedAssetSearch($this->twinfield, self::PATH . '/assets', self::ALL_FIELDS);

        return $pattern === '*' ? $search : $search->pattern($pattern);
    }

    /**
     * @param string $id GUID of the asset.
     *
     * @throws NotFoundException When the company has no asset with this GUID.
     */
    public function get(string $id): FixedAsset
    {
        $json = $this->twinfield->rest('GET', self::PATH . '/assets/' . Guid::of($id), self::ALL_FIELDS)->json();

        return FixedAssetReader::asset($json, $this->twinfield->companyReference());
    }

    /**
     * The history of an asset: created, activated, depreciated, …, oldest first, with the transactions
     * each action posted.
     *
     * @return list<FixedAssetAction>
     *
     * @throws NotFoundException When the company has no asset with this GUID.
     */
    public function actions(string $id): array
    {
        $json = $this->twinfield->rest('GET', self::PATH . '/assets/' . Guid::of($id) . '/actions')->json();

        return array_map(FixedAssetReader::action(...), $json->list('actions'));
    }

    public function classes(string $pattern = '*'): FixedAssetClassSearch
    {
        $search = new FixedAssetClassSearch($this->twinfield, self::PATH . '/classes', self::ALL_FIELDS);

        return $pattern === '*' ? $search : $search->pattern($pattern);
    }

    /**
     * @throws NotFoundException When the company has no class with this GUID.
     */
    public function getClass(string $id): FixedAssetClass
    {
        $path = self::PATH . '/classes/' . Guid::of($id);
        $json = $this->twinfield->rest('GET', $path, self::ALL_FIELDS)->json();

        return FixedAssetReader::fixedAssetClass($json, $this->twinfield->companyReference());
    }

    public function groups(): FixedAssetGroupSearch
    {
        return new FixedAssetGroupSearch($this->twinfield, self::PATH . '/groups');
    }

    /**
     * @throws NotFoundException When the company has no group with this GUID.
     */
    public function getGroup(string $id): FixedAssetGroup
    {
        $path = self::PATH . '/groups/' . Guid::of($id);
        $json = $this->twinfield->rest('GET', $path, self::ALL_FIELDS)->json();

        return FixedAssetReader::group($json, $this->twinfield->companyReference());
    }

    /**
     * Queues a reconciliation report; Twinfield makes it in the background. Returns the report ID for
     * isReconciliationReportReady() and downloadReconciliationReport().
     *
     * @throws UnexpectedResponseException When Twinfield gives no report ID.
     */
    public function queueReconciliationReport(ReconciliationReportRequest $request): string
    {
        $path = self::PATH . '/backgroundreports/reconciliation';
        $response = $this->twinfield->rest('POST', $path, $request->toQuery());
        $location = $response->header('Location');

        if (preg_match('~/backgroundreports/([^/?]+)~', $location, $matches) !== 1) {
            throw new UnexpectedResponseException(sprintf(
                'The reconciliation report was queued (HTTP %d) without a report ID in Location: "%s".',
                $response->status(),
                $location,
            ));
        }

        return rawurldecode($matches[1]);
    }

    /**
     * True when the report is ready (HTTP 303), false while Twinfield still makes it (HTTP 200).
     */
    public function isReconciliationReportReady(string $reportId): bool
    {
        $response = $this->twinfield->rest('GET', self::reportPath($reportId) . '/status');

        return $response->status() === 303;
    }

    /**
     * The report, as JSON.
     *
     * @throws NotFoundException When the report is not ready or does not exist.
     */
    public function downloadReconciliationReport(string $reportId): RestFile
    {
        return RestFile::fromResponse(
            $this->twinfield->rest('GET', self::reportPath($reportId) . '/download'),
            'reconciliation-report.json',
        );
    }

    private static function reportPath(string $reportId): string
    {
        return self::PATH . '/backgroundreports/' . rawurlencode($reportId);
    }
}
