---
title: Fixed assets
description: The new fixed assets module of Twinfield on the REST API - assets, depreciation per regime, history, classes, groups and the reconciliation report - and how it differs from the classic module.
---

Twinfield has two fixed assets modules, and they share no data:

| Module | API | SDK |
|---|---|---|
| New fixed assets module | [REST API](/guides/rest-api/) (Fixed Assets) | `$twinfield->fixedAssets()`, this page |
| Classic fixed assets module | XML web services, dimension type `AST`, asset methods | `$twinfield->classicFixedAssets()` and `classicAssetMethods()`, see [Fixed assets (classic)](/guides/master-data/#fixed-assets-classic) |

Twinfield is phasing out the classic module. A company uses one of the two: an asset in the new module doesn't show up in the classic one, and the other way round. When `fixedAssets()->search()` finds nothing, the company probably still uses the classic module.

The new module is read-only through the API: the REST API can read the assets, their history, classes and groups, and make a reconciliation report. All need the scope `readFixedAssets`.

## Assets

```php
use SolveBeam\Twinfield\FixedAsset\FixedAssetStatus;
use SolveBeam\Twinfield\FixedAsset\Regime;

$fixedAssets = $twinfield->company('1000')->fixedAssets();

foreach ($fixedAssets->search()->status(FixedAssetStatus::Active)->all() as $summary) {
    echo $summary->code, ' ', $summary->description, ' ', $summary->netBookValue, PHP_EOL;
}

$asset = $fixedAssets->get($summary->id);
$fiscal = $asset->depreciation(Regime::Fiscal);
```

`search()` gives `FixedAssetSummary` objects: code, description, status, book value and purchase value (of the youngest regime), last depreciated period, investment and first use date, dispose date and price, and `postingErrorMessage` when Twinfield could not post the last depreciation. Filters: `pattern()`, `status()`, `regime()`, `inClass()`, `inGroup()`, `atLocation()`, `ofType()`, `taxReason()`, `asOf()` (class, group and location in a period), `firstUsedBetween()`, `investedBetween()` and `amountBetween()`.

`get($id)` reads the whole asset as a `FixedAsset`:

| Property | |
|---|---|
| `depreciationSettings` | Per regime (fiscal, commercial, economic): start period and date, remainder value, stop value, and the depreciation methods over time (method, lifetime and lifetime type, from which period) |
| `purchaseValues` | The parts of the purchase value per regime, with the period they count from and the origin |
| `classes` | The class per year (GUID); `getClass()` reads it |
| `costCenters`, `locations` | Per period (GUID) |
| `assetTypes` | The asset types (three levels) per year |
| `freeTexts`, `licensePlate`, `serialNumber`, `taxReasonId`, `taxReasonAmount` | Labels |
| `parents`, `children` | Assets it was split or merged from or into |
| `transactionIds`, `attachmentIds` | Linked transactions and attachments (GUIDs) |
| `currentPeriodNumbers`, `nextPeriodToDepreciate`, `lastMimicPeriod`, `lastRevaluationPeriod` | Where the depreciation is |
| `purchaseValueAccount` | The balance sheet account, with GUID, code and name |

References to cost centers, suppliers and projects are GUIDs: `$twinfield->ledgerAccounts()->get($id)` gives the code.

## History

`actions($id)` lists what happened to an asset, oldest first: created, activated, depreciated, revalued, disposed, split, merged, … (`FixedAssetActionType`), with the user, the moment and the transactions the action posted.

```php
foreach ($fixedAssets->actions($asset->id) as $action) {
    echo $action->timestamp->format('Y-m-d'), ' ', $action->type->value, ' by ', $action->user, PHP_EOL;
}
```

## Classes and groups

A class (e.g. buildings, vehicles) has the default depreciation per regime and the accounts of its assets. `classes()` lists them (without code: the list of Twinfield doesn't have it), `getClass($id)` reads one with code, accounts and depreciation.

A group holds assets that are depreciated together, with a stop value per year and regime: `groups()` and `getGroup($id)`.

## Reconciliation report

Twinfield makes the reconciliation report in the background:

```php
use SolveBeam\Twinfield\FixedAsset\ReconciliationReportRequest;
use SolveBeam\Twinfield\Period\Period;

$id = $fixedAssets->queueReconciliationReport(new ReconciliationReportRequest(Period::of(2026, 12), Regime::Fiscal));

while (!$fixedAssets->isReconciliationReportReady($id)) {
    sleep(5);
}

$report = $fixedAssets->downloadReconciliationReport($id)->contents; // JSON
```

The report is not verified live yet: the test user gets HTTP 403 when queueing it, also with the scope `readFixedAssets`.

## Examples

- [List fixed assets](/examples/fixed-assets/list-fixed-assets/)
- [Read fixed asset](/examples/fixed-assets/read-fixed-asset/)
