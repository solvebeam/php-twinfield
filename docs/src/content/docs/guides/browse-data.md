---
title: Browse data
description: Get financial data out of Twinfield with browse codes, such as transaction lines and balances per general ledger account, with a fluent query and strict result rows.
---

Twinfield gives financial data, such as general ledger transaction lines, open items, balances and time & expenses, with **browse data**. Each kind of data is a **browse code**: a predefined definition with fields, some of which can be a selection criterion. You send a query with the fields you want and the criteria, and get all rows back.

| Twinfield documentation | Resource | Status |
|---|---|---|
| [Browse data](https://developers.twinfield.com/documentation/api/request-data/browse-data/) | `$twinfield->browse()`, `$twinfield->browseDefinitions()` | Query, read browse definitions, list browse fields |

Browse data belongs to a company, so the client needs one: `Twinfield::create($accessTokenProvider)->company('1000')`.

## At a glance

```php
use SolveBeam\Twinfield\BrowseData\BrowseCode;

$result = $twinfield->company('1000')->browse(BrowseCode::GeneralLedgerDetailsV3)
    ->columns(
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.line.dim1',
        'fin.trs.line.valuesigned',
        'fin.trs.line.description',
    )
    ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
    ->equal('fin.trs.head.status', 'normal')
    ->get();

foreach ($result as $row) {
    echo $row->string('fin.trs.head.code'), ' ', $row->int('fin.trs.head.number'), ' ',
        $row->string('fin.trs.line.dim1'), ' ', $row->amount('fin.trs.line.valuesigned'), PHP_EOL;
}
```

The steps, as in the [Twinfield documentation](https://developers.twinfield.com/documentation/api/request-data/browse-data/#steps-to-be-taken):

1. **Choose a browse code**, e.g. `030_3` for general ledger transaction lines or `040_1` for balances per account. See [Browse codes](/guides/browse-codes/) for all codes.
2. **Read the browse definition** to see the fields and which of them can be a criterion. The definitions of all browse codes are in [`browse-definitions/`](https://github.com/solvebeam/php-twinfield/tree/main/browse-definitions) in the repository, so you don't have to ask Twinfield.
3. **Fill in the criteria** and choose the columns.
4. **Send the query** with `get()`.

Steps 1 to 3 are done once, while you write the code.

## Browse codes

`BrowseCode` has all codes of the documentation, e.g. `BrowseCode::GeneralLedgerDetailsV3` for `030_3`. See [Browse codes](/guides/browse-codes/) for the list with an example of each code, including the request and response XML, and the most used fields per browse code from Twinfield's BrowseCodeDetails overview.

`browse()` also takes a string, e.g. `browse('030_3')`, so a code Twinfield adds later works without an SDK update.

## Browse definitions and fields

```php
$definition = $twinfield->browseDefinitions()->get(BrowseCode::GeneralLedgerDetailsV3);

foreach ($definition->columns as $column) {
    echo $column->field, ' ', $column->label, $column->ask ? ' (criterion, ' . $column->operator->value . ')' : '', PHP_EOL;
}

$fields = $twinfield->browseDefinitions()->fields();   // data type and values of every browse field
```

- A `BrowseDefinitionColumn` has the `field`, the `label` (in the language of the user), whether it can be a criterion (`ask`), the proposed `operator` and the proposed `from` and `to` values.
- A column with `visible` false is meant as criterion only, e.g. `fin.trs.head.yearperiod` in 030_3. Asking for it as column can give a server error.
- A `BrowseField` has the `dataType` (`string`, `special`, `value`, `long`, `decimal`, `date`, `datetime`), the Finder type to look up values (`finder`, e.g. `TRS` for day books) and, for `special` fields, the `options`: the values to use in a criterion, e.g. `draft`, `temporary`, `final`, `inuse` and `normal` (provisional and final) for `fin.trs.head.status`.

See [Read browse definition](/examples/browse-data/read-browse-definition/) and [List browse fields](/examples/browse-data/list-browse-fields/).

## The query

`browse()` returns an immutable `BrowseQuery`: every method returns a new query, so a base query can be reused, e.g. for each period.

| Method | Sends | Example |
|---|---|---|
| `columns(...$fields)` | The fields in the result, in this order | `columns('fin.trs.head.code', 'fin.trs.line.valuesigned')` |
| `equal($field, $value)` | `equal` | `equal('fin.trs.head.code', 'VRK')` |
| `between($field, $from, $to)` | `between`, both included | `between('fin.trs.head.yearperiod', '2026/01', '2026/12')` |
| `notBetween($field, $from, $to)` | `notbetween` | `notBetween('fin.trs.line.dim1', '0000', '0999')` |
| `like($field, $pattern)` | `like`: `*` any characters, `?` one | `like('fin.trs.head.code', 'VRK*')` |
| `betweenDates($field, $from, $to)` | `between` with dates `yyyymmdd` | `betweenDates('fin.trs.head.date', $first, $last)` |
| `betweenDateTimes($field, $from, $to)` | `between` with moments `yyyymmddhhmmss`, converted to UTC | `betweenDateTimes('fin.trs.head.modified', $lastSync, $now)` |
| `sortBy($field, $order)` | `<sort>`, at most 3 fields | `sortBy('fin.trs.head.number', SortOrder::Descending)` |

Values can be a string, an int, a `Period` (`2026/03`, see [Periods](/guides/periods/)) or a `Regime`. A criterion on a field that is not one of the columns only selects: the field is not in the result. Setting a criterion on a field again replaces it.

Keep in mind:

- **Twinfield does not apply the proposed criteria of the definition.** Without a criterion on `fin.trs.head.status` you get all transactions, also drafts. Drafts have number `-1`, so they don't have a unique key. Use `equal('fin.trs.head.status', 'normal')` (provisional and final) or `'final'`.
- **Only ask for the columns you need.** Twinfield returns all rows at once, without paging. A query over many years of a large company can be tens of megabytes. Ask for one year, or one period, at a time.
- **The data is that of the company of the client.** A criterion on `fin.trs.head.office` makes Twinfield return the data of another company, so the SDK refuses it: use `company()` instead.
- **Sorting does not work for every browse code.** 000 sorts, 030_3 ignores the sort fields. Sort in PHP when the order matters.
- `toXml()` gives the request, e.g. to log it or to try it in Postman. The SDK always sends `optimize="true"`, which makes the response about three times smaller.

### Regimes

Companies that report under more than one regime (fiscal, commercial, economic) have transaction lines per regime, besides the generic lines. Several browse codes have a regime field (`fin.trs.head.regime` in 030_3, `fin.trs.head.balanceregimeprompt` in 040_1). Searching for `Fiscal`, `Commercial` or `Economic` gives the lines of that regime plus all generic lines; `Generic` gives only the generic lines. In 040_1, 050_1 and 060 the lines are grouped and summed, and the regime is required: without it Twinfield uses `Generic`.

```php
use SolveBeam\Twinfield\Budget\Regime;

$query = $twinfield->browse(BrowseCode::AnnualReportTotals)
    ->columns('fin.trs.line.dim1', 'fin.trs.line.basevaluesigned')
    ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
    ->equal('fin.trs.head.balanceregimeprompt', Regime::Fiscal);
```

## The result

`get()` returns a `BrowseResult` with the `columns` (field and type) and the `rows`. It is countable and iterable. Read a value of a `BrowseRow` by its field, with the reader for its type:

| Reader | Column type | Returns |
|---|---|---|
| `string()`, `nullableString()` | any | The value; `string()` throws when it is empty |
| `int()` | `Number`, `Decimal` | A whole number, e.g. the transaction number |
| `nullableDecimal()` | `Number`, `Decimal` | A numeric string or null, e.g. the quantity |
| `amount()` | `Value` | An `Amount`, e.g. `-121.00` |
| `date()`, `nullableDate()` | `Date` | A `DateTimeImmutable` from `yyyymmdd` |
| `dateTime()` | `Datetime` | A `DateTimeImmutable` in UTC from `yyyymmddhhmmss` |
| `period()` | any | A `Period` from `yyyy/pp` |
| `displayName()` | any | The value as Twinfield shows it, in the language of the user (e.g. `Definitief` for `final`), or null |
| `values()` | – | All values as Twinfield returned them, by field |

A reader throws an `InvalidArgumentException` for a field that is not a column, or a column of another type, and an `UnexpectedResponseException` for a value that does not fit.

Each row has the `company` and, for transaction lines, the `transactionLine`: a `TransactionLineReference` with the day book, number and line number. Together with the company that is the key of a transaction line, e.g. in a shadow database. `transactionLine->transaction()` gives the `TransactionReference` to read the whole transaction. Browse codes with totals (040_1) have no transaction line: it is null.

The SDK checks the response: it throws an `UnexpectedResponseException` when the columns differ from what was asked, when a row belongs to another company, or when Twinfield returned fewer rows than its total. Twinfield's own errors, e.g. for an unknown field (`Veld fin.trs.nope bestaat niet.`), are thrown as `ProcessXmlException`.

## Keeping a shadow database

Twinfield asks integrations that need transaction data often to keep a copy (a shadow database) instead of downloading everything again and again. See [Keeping a shadow database with transactions](/knowledge-base/shadow-database-with-transactions/) for the article of Twinfield and the examples:

1. [Initial load](/examples/shadow-database/initial-load/): all transaction lines with 030_3, one year at a time.
2. [Incremental load](/examples/shadow-database/incremental-load/): the lines changed since the last download (`fin.trs.head.modified`) and the deleted transactions.
3. [Validate balances](/examples/shadow-database/validate-balances/): compare the balance per general ledger account with 040_1.

## Not supported

| Part of the API | Why |
|---|---|
| `outputformat="CSVEXCEL"` | Made for Excel: text as formulas (`="VRK"`), and display names instead of values (`Debet` instead of `debit`). Use the XML result. |
| `<list><type>budgets</type>` (budget vs. actuals, old manual) | Use the BudgetService: [Budgets](/guides/master-data/#budgets), `$twinfield->budgets()`. |
| `<read><type>activeanalysis</type>` (Twinfield Analysis cubes, old manual) | Not needed: the cubes are browse codes. |
| Operators `lessthan`, `in`, `notin` | `lessthan` is in the old manual, `in` and `notin` in a Twinfield error message, but all three give a server error. |

Where the live responses differ from the documentation, see [Differences with the documentation](/guides/documentation-differences/#browse-data).
