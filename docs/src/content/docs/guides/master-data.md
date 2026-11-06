---
title: Master data (dimensions)
description: Search, read, create, update and delete master data such as activities, with typed models and clear errors.
---

Master data in Twinfield, such as activities, projects, customers and suppliers, are **dimensions**. Each has a dimension type, e.g. `ACT` for activities. The SDK searches them with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/) and reads and writes them with the [XML web services](/guides/process-xml/). You work with typed objects and never write XML yourself.

| Dimension type | Resource | Status |
|---|---|---|
| Activities (`ACT`) | `$twinfield->activities()` | Search, read, create, update, delete, activate |
| Projects (`PRJ`), customers (`DEB`), suppliers (`CRD`), … | – | Not yet; use [`processXml()`](/guides/process-xml/) |

Other master data works the same way, but is not a dimension:

| Master data | Resource | Status |
|---|---|---|
| [Asset methods](#asset-methods) | `$twinfield->assetMethods()` | Search, read, create, update, delete, activate |

Dimensions belong to a company, so the client needs one: `Twinfield::create($accessTokenProvider)->company('1000')`.

## At a glance

```php
use SolveBeam\Twinfield\Activity\ActivityInput;
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\Quantity;

$activities = $twinfield->activities();

// Search
foreach ($activities->search('A*')->inCode()->all() as $summary) {
    echo $summary->code, ' ', $summary->name, PHP_EOL;
}

// Read
$activity = $activities->get('A001');   // or find(): null when it does not exist

// Create
$activities->create(
    ActivityInput::create('A100', 'Consultancy')
        ->customer('1010')
        ->rate('T001', ChangeMode::Disallow)
        ->billable(true)
        ->quantity(Quantity::create('KM')->rate('KM')),
);

// Update
$activities->update($activity->edit()->name('Development'));

// Delete
$activities->delete('A100');
```

## Searching

`search($pattern)` returns an immutable `CodeNameSearch`: every method gives a new search, so you can keep a base search and refine it.

| Method | What it does |
|---|---|
| `search('A*')` | Pattern: `*` matches anything, `?` one character. Default `*`. |
| `inCodeAndName()` / `inCode()` / `inName()` | Where the pattern must match. Default: code and name. |
| `modifiedSince($date)` | Only dimensions changed on or after this moment. |
| `group('DEV*')` | Only dimensions in a dimension group; wildcards are allowed. |
| `limit(10)` / `offset(20)` | Page size (default 100) and results to skip. |
| `get()` | One page: `totalRows` and the summaries (code and name). |
| `all()` | All results over all pages, page by page while you iterate. |
| `count()` | Number of matches, with one small request. |

Search results only have the code and name. Use `get($code)` for everything else.

## Reading

`get($code)` returns the full read model, e.g. an `Activity`. Read models are strict: required values are never `null`, and a response that does not match what the SDK expects throws an `UnexpectedResponseException` with the path, e.g. `dimension/projects/customer/@locked is missing.` A value is only `null` when Twinfield allows it to be empty, such as a short name that was never set.

When the code does not exist, `get()` throws a `DimensionNotFoundException` with `dimensionType`, `dimensionCode` and Twinfield's `reason`. `find($code)` returns `null` instead. A code that does not match the company's code format (for activities by default `A[0-9][0-9][0-9]`) cannot exist, so it is also "not found".

## Creating and updating

You describe what to save with an input object, e.g. `ActivityInput`. It is separate from the read model: you can only set what Twinfield lets you change, and invalid values (a name that is too long, a code with lowercase letters) throw an `InvalidArgumentException` before anything is sent.

Twinfield has one request for both: it creates the dimension when the code is new and changes it otherwise. The SDK makes the difference explicit:

| Method | When the code is new | When the code exists | Requests |
|---|---|---|---|
| `create($input)` | Creates | Throws `DimensionAlreadyExistsException` | Read + save |
| `update($input)` | Throws `DimensionNotFoundException` | Changes | Read + save |
| `save($input)` | Creates | Changes | Save |

To change a dimension, start from what Twinfield has: `$activity->edit()` gives an input with all current values. The SDK always sends every value, so a value you remove (e.g. `shortName(null)`) is also removed in Twinfield.

All three return the saved read model from Twinfield's response. When Twinfield refuses the request, you get a `ProcessXmlException` with the message per element, e.g. `dimension/financials/vatcode: Btw-code XX bestaat niet.`

## Who may change a setting: ChangeMode

Time & expenses settings such as the customer, rate, authoriser and billable have a `ChangeMode`: whether users may change the value in their timesheet, or whether it comes from the project or user level.

| ChangeMode | Meaning | XML |
|---|---|---|
| `Allow` | Fixed value, users may change it | `locked="false" inherit="false"` |
| `Disallow` | Fixed value, users may not change it | `locked="true" inherit="false"` |
| `Inherit` | From the project or user level | `locked="true" inherit="true"` |

New activities inherit every setting until you set it. Inherited settings have no value of their own, so `code` (and `billable`) are `null`.

## Deleting

`delete($code)` returns what Twinfield did:

- `DeleteResult::Deleted`: the dimension is deleted (status `deleted`).
- `DeleteResult::Hidden`: the dimension is in use, so Twinfield hid it instead (status `hide`).

`activate($code)` makes a deleted or hidden dimension active again. A deleted dimension keeps its code: `create()` with the same code throws `DimensionAlreadyExistsException`.

## Asset methods

An asset method says how fixed assets are depreciated and on which accounts. It is not a dimension, but `$twinfield->assetMethods()` has the same methods as the dimension resources: `search()`, `get()`, `find()`, `create()`, `update()`, `save()`, `delete()` and `activate()`. The differences:

- Search uses the Finder type `ASM` and gives `AssetMethodSummary` results. `group()` is for dimensions only.
- Twinfield has no `<read>` for asset methods. `get()` sends an `<assetmethod>` with only the company and code, which is how Twinfield reads one.
- Not found is an `AssetMethodNotFoundException`, an existing code on `create()` an `AssetMethodAlreadyExistsException`.
- `create()`, `update()` and `save()` read the asset method back after saving, so you always get the complete `AssetMethod`.
- The calculation is set with one method: `linear(60)`, `linearPercentage('20')`, `reducingBalance('25')` or `noDepreciation()`. Percentages are strings with a dot (`'12.5'`), never floats. Calculation settings that are not set are not sent, so Twinfield keeps them.

```php
use SolveBeam\Twinfield\AssetMethod\AssetMethodInput;
use SolveBeam\Twinfield\AssetMethod\DepreciationStart;

$assetMethods = $twinfield->assetMethods();

$method = $assetMethods->create(
    AssetMethodInput::create('LAPTOP', 'Laptops')
        ->linear(36)
        ->depreciateFrom(DepreciationStart::FromPurchaseDate)
        ->purchaseValueAccount('0501')
        ->cumulativeDepreciationAccount('0135')
        ->depreciationCostsAccount('4730')
        ->freeText(1, 'Serial number'),
);

$assetMethods->update($method->edit()->reducingBalance('25'));
```

## Examples

- [List activities](/examples/activities/list-activities/)
- [Search activities](/examples/activities/search-activities/)
- [Read activity](/examples/activities/read-activity/)
- [Create activity](/examples/activities/create-activity/)
- [Update activity](/examples/activities/update-activity/)
- [Delete activity](/examples/activities/delete-activity/)
- [List asset methods](/examples/asset-methods/list-asset-methods/)
- [Read asset method](/examples/asset-methods/read-asset-method/)
- [Create asset method](/examples/asset-methods/create-asset-method/)
- [Update asset method](/examples/asset-methods/update-asset-method/)
- [Delete asset method](/examples/asset-methods/delete-asset-method/)
