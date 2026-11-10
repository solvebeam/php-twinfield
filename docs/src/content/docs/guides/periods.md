---
title: Periods
description: Read the years and periods of a company, find the period of a transaction, and open, close, rename and create periods and years.
---

A financial year in Twinfield is divided into periods, usually the 12 months, plus special periods for the opening balance and the year end. Every transaction is booked in a period, e.g. `2026/11`. The [periods service](https://developers.twinfield.com/documentation/api/miscellaneous/periods/) (PeriodService) reads the years and periods of a company, and opens, closes, renames and creates them.

| Twinfield documentation | Resource | Status |
|---|---|---|
| [Periods](https://developers.twinfield.com/documentation/api/miscellaneous/periods/) `GetYears`, `GetPeriods` | `$twinfield->periods()->years()`, `list()`, `get()`, `find()` | Verified live (read-only) |
| [Periods](https://developers.twinfield.com/documentation/api/miscellaneous/periods/) `ChangeName` | `changeName()` | Tested offline; `composer test:write` changes a name live and restores it |
| [Periods](https://developers.twinfield.com/documentation/api/miscellaneous/periods/) `ChangeEndDate`, `OpenPeriod`, `ClosePeriod`, `CreateYear`, `DeleteYear`, `ResetYears` | `changeEndDate()`, `open()`, `close()`, `createYear()`, `deleteYear()`, `resetYears()` | Only tested offline |

The PeriodService is a [Command & Query web service](https://developers.twinfield.com/documentation/api/types/command-and-query-webservices). It always works on one company, given in the request header: `$twinfield->company('1000')->periods()`. Without a company, the SDK throws a `LogicException` before sending.

## At a glance

```php
use SolveBeam\Twinfield\Period\Period;

$periods = $twinfield->company('1000')->periods();

$periods->years();                                  // [2024, 2025, 2026]

foreach ($periods->list(2026) as $details) {
    echo $details->period, ' ', $details->name ?? '-', ' ', $details->open ? 'open' : 'closed', PHP_EOL;
}

$transaction = $twinfield->journalTransactions()->get('MEMO', 202600012);

$periods->get($transaction->header->period)->open;  // can the transaction still be changed?

$periods->close(Period::of(2026, 1));
```

```text
2026/00 Openingsbalans 2026 open
2026/01 januari 2026 open
…
2026/12 december 2026 open
2026/55 Voorafgaande journaalposten 2026 open
```

## Period and PeriodDetails

The SDK has two classes for a period:

- **`Period`** is the year and the period number, e.g. `2026/11`. It is the value Twinfield uses in transactions (`<period>`), browse data (`fin.trs.head.yearperiod`), budgets, general ledger accounts (`activeFrom`, `activeUntil`) and fixed assets. Create one with `Period::of(2026, 11)` or `Period::fromString('2026/11')`; it prints as `2026/11`. Compare two periods with `equals()`.
- **`PeriodDetails`** is what the PeriodService returns for a period: `period` (a `Period`), `name`, `endDate`, `open` and `company`.

So the period of a transaction is a `Period`, and `periods()->get($period)` gives its `PeriodDetails`:

```php
$period = $transaction->header->period;             // Period 2026/03
$details = $twinfield->periods()->get($period);     // PeriodDetails

$details->name;                                     // 'maart 2026'
$details->endDate;                                  // DateTimeImmutable 2026-03-31
$details->open;                                     // true
```

`get()` reads all periods of the year. For many transactions, call `list($year)` once per year and look up the periods yourself: see [Period of transaction](/examples/periods/period-of-transaction/).

## Years and special periods

`years()` returns the years of the company as integers. `list($year)` returns all periods of a year, sorted by number. Besides the regular periods (1–12 for a year with 12 periods), a year has special periods:

| Period | Name in Twinfield (Dutch) | Used for |
|---|---|---|
| 0 | Openingsbalans | The opening balance, e.g. day book `BEGINBALANS` |
| 55 | Voorafgaande journaalposten | Year end bookings, e.g. day book `VJP` |

Special periods have no end date: `endDate` is `null`. The end date of a regular period is its last day; a financial year does not have to follow the calendar year.

Period names are in the language of the company. Twinfield returns an empty name (`<Name/>`) for all periods of some years, in one test company for 49 of its 61 years (mostly before 2013): `name` is `null` then.

## Open and closed periods

A closed period cannot be booked in. `open` tells whether a period is open; `open($period)` and `close($period)` change it.

```php
$periods->close(Period::of(2026, 1));
$periods->open(Period::of(2026, 1));
```

Saving a transaction in a closed period is refused with a `ProcessXmlException`. The exact message is not verified yet.

## Changing periods and years

| Method | Command | What it does |
|---|---|---|
| `changeName($period, $name)` | `ChangeName` | Changes the name of a period |
| `changeEndDate($period, $endDate)` | `ChangeEndDate` | Changes the end date of a period |
| `open($period)`, `close($period)` | `OpenPeriod`, `ClosePeriod` | Opens or closes a period |
| `createYear($numberOfPeriods)` | `CreateYear` | Creates a new year, e.g. with 12 periods |
| `deleteYear($year)` | `DeleteYear` | Deletes a year |
| `resetYears($newYear, $newNumberOfPeriods)` | `ResetYears` | Deletes **all** years and starts again from `$newYear` |

Commands return nothing. Only run `deleteYear()` and `resetYears()` on a test company. See [Rename period](/examples/periods/rename-period/).

## Errors

When Twinfield refuses a query or command, the SDK throws a `CommandQueryFaultException` with `type` `PeriodServiceFault` and an `errorCode` from `PeriodServiceFaultCode`:

```php
use SolveBeam\Twinfield\Period\PeriodServiceFaultCode;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;

try {
    $periods->close(Period::of(2026, 1));
} catch (CommandQueryFaultException $exception) {
    if (PeriodServiceFaultCode::tryFrom($exception->errorCode) === PeriodServiceFaultCode::PeriodCanNotBeClosed) {
        echo $exception->errorMessage, PHP_EOL;
    }
}
```

The codes are `YearNotFound`, `PeriodNotFound`, `YearCanNotBeDeleted`, `InvalidNumberOfPeriods`, `YearCanNotBeCreated`, `EndDateCanNotBeChanged`, `NameCanNotBeChanged`, `PeriodCanNotBeOpened` and `PeriodCanNotBeClosed`. The message is in the language of the user, e.g. `Year 1901 bestaat niet.`

`get()` throws a `PeriodNotFoundException` instead when the year does not exist (`YearNotFound`) or has no period with that number; `find()` returns `null` then.

## Organisation and company

Periods belong to a company. `PeriodDetails::$company` is a `CompanyReference` with the organisation and the company code, so periods of different organisations can be kept apart. See [Organisations and companies](/guides/organisations/).

## Differences with the documentation

See [Periods](/guides/documentation-differences/#periods) in the differences with the documentation.
