---
title: Pay & Collect
description: Payment runs and direct debit runs, from selecting the open invoices to the SEPA file, with the version check every step needs.
---

[Pay & Collect](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/) creates the payments to suppliers and the direct debits from customers that Twinfield shows as betaalrun and incassorun in its interface. A run selects the open invoices, can be changed, is authorised and posted to the in-transfer ledger (tussenrekening), and gives the SEPA file for the bank.

| Twinfield documentation | Resource | Status |
|---|---|---|
| [GetRuns](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#getruns) | `$twinfield->payAndCollect()->list()` | Runs live; the test companies have no runs yet |
| [GetRunInfo](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#getruninfo) | `get()` | Only tested offline, with responses after the WSDL |
| [CreateRun2](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#createrun2), [CreateRunAsync](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#createrunasync) | `create()`, `createAsync()` | Only tested offline |
| [Modify](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#modify) | `modify()` with a `PayRunModification` | Only tested offline |
| [Authorize](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#authorize) | `authorise()` | Only tested offline |
| [Post](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#post-1), [PostAsync](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#postasync) | `post()`, `postAsync()` | Only tested offline |
| [CreateFiles](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#createfiles) | `createFiles()` | Only tested offline |
| [Finish](https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#finish-1) | `finish()` | Only tested offline |

Every operation needs a company: `$twinfield->company('1000')->payAndCollect()`. The write test (`tests/Write/PayAndCollect`) creates, changes, authorises and deletes a run; see [TODO.md](https://github.com/solvebeam/twinfield-php/blob/main/TODO.md).

## At a glance

```php
use SolveBeam\Twinfield\PayAndCollect\PayRunInput;
use SolveBeam\Twinfield\PayAndCollect\PayRunMethod;
use SolveBeam\Twinfield\PayAndCollect\PayRunModification;

$payAndCollect = $twinfield->company('1000')->payAndCollect();

$run = $payAndCollect->create(
    PayRunInput::create('SEPANLCT', new DateTimeImmutable('2026-11-12'), '2026/11')
        ->method(PayRunMethod::SummariseAndDeduct)
        ->maxDueDate(new DateTimeImmutable('2026-11-30')),
)->run;

$version = $payAndCollect->modify(PayRunModification::of($run)->excludeSummary(2));
$version = $payAndCollect->authorise($run, $version);
$version = $payAndCollect->post($run, $version);
$files = $payAndCollect->createFiles($run, $version);

file_put_contents('payments.xml', $files->files[0]);

$payAndCollect->finish($run, $files->version);
```

## Pay types

A run has a pay type, such as `SEPANLCT` (SEPA payments, Netherlands) or `SEPANLDD` (SEPA direct debit, Netherlands). The pay type decides the run type: a payment run (`PayRunType::Payment`) selects purchase invoices, a direct debit run (`DirectDebit`) sales invoices. It also has the defaults for the method, the bank and the custom settings. The pay types of a company are in the [Finder](/guides/finder/):

```php
use SolveBeam\Twinfield\Finder\FinderType;

foreach ($twinfield->finder(FinderType::PaymentTypes)->get()->rows as [$code, $name]) {
    echo $code, ' ', $name, PHP_EOL;
}
```

## Phases

A run goes through these phases (`PayRunPhase`). The status of the phase (`PayRunStatus`) is `Started` while Twinfield works on it, then `Done` or `DoneWithErrors`.

| Phase | Reached with | Next step |
|---|---|---|
| `Select` | `create()` | `modify()`, `authorise()` or `finish()` (delete) |
| `Manipulate` | `modify()` | `modify()`, `authorise()` or `finish()` (delete) |
| `Authorise` | `authorise()` | `post()` or `finish()` (delete) |
| `Post` | `post()` | `createFiles()`; `post()` again after `DoneWithErrors` |
| `Pay` | `createFiles()` | `finish()` (end) |
| `Finalise` | `finish()` | — |

Posting books the payments on the in-transfer ledger and matches them with the invoices: this can't be undone. Until then, `finish()` deletes the run and the invoices can be selected again.

## Listing and reading runs

`list()` gives a `PayRunOverview` per run, without summaries. `get()` reads a `PayRun` with its summaries: one payment or collection per relation and currency (`PayRunSummary`), with the bank account of the relation and the invoices (`PayRunDetail`).

```php
foreach ($payAndCollect->list() as $overview) {
    $run = $payAndCollect->get($overview->payType, $overview->id);

    foreach ($run->summaries as $summary) {
        echo $summary->relation, ' ', $summary->relationBank?->iban, ' ', $summary->value(), PHP_EOL;

        foreach ($summary->details as $detail) {
            echo '  ', $detail->invoiceNumber, ' ', $detail->value, ' of ', $detail->originalValue, PHP_EOL;
        }
    }
}
```

## Creating a run

`PayRunInput::create()` takes the pay type, the pay date and the period to post in. The rest is optional and defaults to the pay type:

- `method()`: `Detailed` (an invoice per payment), `Summarise` (one payment per relation and currency, without credit invoices) or `SummariseAndDeduct` (with credit invoices deducted).
- `bank()`, `directRemove()`, `discountDaysTolerance()`.
- Filters: `dimensions($from, $to)` (suppliers or customers), `maxDueDate()`, `invoiceNumber()`, `currency()`.
- `setting($name, $value)` for a custom setting of the pay type, and `collectionSchema(CollectionSchema::B2B)` for the SEPA direct debit scheme (`SEPANLDDcollectionschema`, the only documented setting).

`create()` uses CreateRun2: it is CreateRun with `DiscountDaysTolerance`, so the SDK does not offer CreateRun. It returns a `CreatedPayRun` with the run and the messages of the selection, e.g. about invoices that were skipped. For a large run, `createAsync()` starts the selection and returns right away; read the run with `list()` or `get()` until its status is `Done`.

## The version of a run

Every step that changes a run sends the version it was last read with: `touched` (how often the run was changed) and `modified`. Twinfield refuses the step when the run was changed in between. The steps return the new version (`PayRunVersion`), with the warnings of the step in `$version->messages`; pass it to the next step. Without a version, a step uses the version of the run it gets.

`modified` is sent back exactly as Twinfield returned it (`$version->modifiedValue`), so no fractions of seconds or time zone are lost.

## Changing a run

Before it is authorised, a run can be changed with a `PayRunModification`: exclude a summary or an invoice, or pay less of an invoice.

```php
$modification = PayRunModification::of($run)
    ->excludeSummary(3)
    ->excludeDetail(1, 2)
    ->value(1, 1, '50.00')
    ->discount(1, 1, '1.00');

$version = $payAndCollect->modify($modification);
```

The SDK refuses a value of zero (exclude the invoice instead), a value above the original value and a negative discount before sending. Modify sends every summary and detail of the run, with the current values for the ones that did not change, because `Value` and `Included` are required for every detail in the WSDL.

## Files

`createFiles()` gives the payment files of a posted run, decoded: one file, or more when the bank limits the number of transactions per file. The SDK does not offer CreateFile, which gives only the first file. Export settings are optional `[name => value]` pairs, as in the manual (`FileType`, `Origin` for ClieOp03).

```php
$files = $payAndCollect->createFiles($run, $version);

foreach ($files->files as $index => $contents) {
    file_put_contents(sprintf('payments-%d.xml', $index + 1), $contents);
}
```

## Errors

A message of type `Error` is thrown as a `PayAndCollectException`, with the operation and the messages. The code of a message is always `UnknownTextualMessage`, so only the text tells what went wrong. Warnings and informational messages are returned: in `CreatedPayRun::$messages`, `PayRunVersion::$messages` and the result of `finish()` and `createAsync()`.

```php
use SolveBeam\Twinfield\PayAndCollect\PayAndCollectException;

try {
    $payAndCollect->authorise($run);
} catch (PayAndCollectException $exception) {
    echo $exception->operation, ': ', $exception->messages[0]->text, PHP_EOL;
}
```

## Examples

- [List pay runs](/examples/pay-and-collect/list-pay-runs/)
- [Get pay run](/examples/pay-and-collect/get-pay-run/)
- [Create pay run](/examples/pay-and-collect/create-pay-run/)
- [Finish pay run](/examples/pay-and-collect/finish-pay-run/)
- [Pay run lifecycle](/examples/pay-and-collect/pay-run-lifecycle/)

See also the [differences with the documentation](/guides/documentation-differences/#pay--collect).
