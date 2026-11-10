---
title: Matching
description: Match existing transaction lines with each other, e.g. an invoice and its payment, fully, partly or with a discount, and undo a match by its match number.
---

Matching is what Twinfield calls afletteren in its interface: linking transaction lines that settle each other, such as a sales invoice and the payment of it. A matched invoice is paid; its open amount is zero. The [Matching](https://developers.twinfield.com/documentation/api/miscellaneous/matching/) request matches existing lines, [Undo match](https://developers.twinfield.com/documentation/api/miscellaneous/undo-match/) undoes a match set.

| Twinfield documentation | Resource | Status |
|---|---|---|
| [Matching](https://developers.twinfield.com/documentation/api/miscellaneous/matching/) (XML web services, `<match>`) | `$twinfield->matching()->match()` | Only tested offline, with the requests of the documentation |
| [Undo match](https://developers.twinfield.com/documentation/api/miscellaneous/undo-match/) (`matching.asmx`) | `$twinfield->matching()->undo()` | Only tested offline, with responses after the WSDL |
| [Browse data](https://developers.twinfield.com/documentation/api/request-data/browse-data/) 100 and 200 | `fin.trs.line.matchnumber`, `matchstatus`, `matchdate` | The query runs live |
| [REST API](https://api.accounting.twinfield.com/Api/swagger/ui/index#!/Bookkeeping32Audit32Trails) audit trail | `$twinfield->matching()->auditTrail()`, `auditTrailSince()` | Verified live |

Both need a company: `$twinfield->company('1000')->matching()`.

## At a glance

```php
use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\Matching\MatchInput;
use SolveBeam\Twinfield\Matching\WriteOff;

$matching = $twinfield->company('1000')->matching();

// Line 2 of a cash payment pays line 1 (the total line) of a sales invoice.
$matching->match(
    MatchInput::customers(new DateTimeImmutable('2026-11-10'))
        ->line('KAS', 202600013, 2)
        ->line('VRK', 202600069, 1),
);

$matching->undo(DimensionTypeCode::AccountsReceivable, '1000', 12);
```

## Match codes

The match code says which kind of lines are matched. Start the set with the method of the match code:

| Match code | Method | Lines of | Dimension type for `undo()` |
|---|---|---|---|
| `070` | `MatchInput::suspenseAccounts()` | Suspense accounts (tussenrekeningen) | `BalanceSheet` (`BAS`) |
| `170` | `MatchInput::customers()` | Customers | `AccountsReceivable` (`DEB`) |
| `270` | `MatchInput::suppliers()` | Suppliers | `AccountsPayable` (`CRD`) |
| `370` | `MatchInput::projects()` | Projects | `Projects` (`PRJ`) |

`MatchCode::dimensionType()` gives the dimension type of a match code.

## Lines

A line is the day book, the transaction number and the line number: `line('VRK', 202600069, 1)`. Line 1 is the total line of a sales or purchase invoice; for a bank, cash or journal transaction, use the `id` of the detail line (`$transaction->details[0]->id`). A set needs at least two lines.

Without amounts, Twinfield matches the open amount of each line. A line can have:

- **A match value**, only for a partial payment: an invoice of 121.00 paid with 119.00.

  ```php
  MatchInput::customers($date)
      ->line('KAS', 202600014, 2)
      ->line('VRK', 202600070, 1, matchValue: '119.00');
  ```

- **A write-off**, for the difference: `WriteOff::discount()`, `WriteOff::writeOff()` or `WriteOff::currency()` (an exchange rate difference). Twinfield needs the type to know what to do with the difference.

  ```php
  MatchInput::customers($date)
      ->line('KAS', 202600015, 2)
      ->line('VRK', 202600071, 1, writeOff: WriteOff::discount('2.00'));
  ```

Use a negative amount on a credit line, as the documentation says. Amounts are `Amount|string` with two decimals; zero is refused.

Lines from browse data have a `TransactionLineReference` (`$row->transactionLine`): add them with `lineReference()`.

```php
$input = $input->lineReference($row->transactionLine, matchValue: '119.00');
```

Twinfield refusals are thrown as `ProcessXmlException`.

## Matching within a bank or cash transaction

To match a line while creating a bank or cash transaction, add a `Transaction\MatchSetInput` to the detail line with `DetailLineInput::match()`, see [Matching](/guides/transactions/#matching) in the transactions guide. That is part of the transaction request; `matching()` is for lines that already exist.

## Match numbers and undo

Every match set has a number per dimension: match 12 of customer 1000. Browse data has it in `fin.trs.line.matchnumber`, with `fin.trs.line.matchstatus` `matched` and `fin.trs.line.matchdate`:

```php
use SolveBeam\Twinfield\BrowseData\BrowseCode;

$result = $twinfield->browse(BrowseCode::CustomerTransactions)
    ->columns('fin.trs.line.dim2', 'fin.trs.head.code', 'fin.trs.head.number', 'fin.trs.line.matchnumber')
    ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
    ->between('fin.trs.line.dim2', '1000', '1000')
    ->equal('fin.trs.line.matchstatus', 'matched')
    ->get();
```

Use browse code 200 (`BrowseCode::SupplierTransactions`) for suppliers.

`undo()` takes the dimension type, the dimension and the match number. Twinfield answers with messages; the SDK throws on an `Error`:

| Code | Exception |
|---|---|
| `MatchNotFound` | `MatchNotFoundException` |
| `DimensionNotFoundByType`, `DimensionDeleted`, `DimensionNotFoundByLevel`, `MatchingTransactionsCouldNotBeDeleted` | `MatchingException` |

`MatchNotFoundException` extends `MatchingException`; `$exception->messages` has the `UndoMatchMessage`s with `code` (`UndoMatchMessageCode`) and `text`.

## Audit trail

The REST API keeps an audit trail of the matches: every match and undone match, with the match number, the dimension, the user and the matched lines and amounts. It needs the scope `readBookkeepingMatches`.

```php
foreach ($twinfield->matching()->auditTrailSince(new DateTimeImmutable('-1 day')) as $event) {
    echo $event->eventType->value, ' ', $event->matchNumber, ' on ', $event->dimensionCode, PHP_EOL;

    foreach ($event->lines as $line) {
        echo '  ', $line->dayBook, ' ', $line->number, '/', $line->lineId, ' ', $line->matchedAmount, PHP_EOL;
    }
}
```

`auditTrail($since, $limit)` reads one page of at most 200 events, oldest first. The trail has no offset: the next page starts at the `logTimestamp` of the last event. `since` is inclusive, so that page repeats the events with that timestamp; `auditTrailSince()` pages for you and gives each event once (by `eventId`).

`Unmatched` events (`MatchingEventType::Unmatched`) have no match date. The Swagger definition describes the page as `{events, nextTimestamp}`; live it is `{items}`, without a next timestamp.

## Examples

- [Matching audit trail](/examples/matching/matching-audit-trail/): the matches and undone matches since a date (REST API).
- [Match transactions](/examples/matching/match-transactions/): match an invoice with its payment (write).
- [List match sets](/examples/matching/list-match-sets/): the matched lines of a customer with their match number.
- [Undo match](/examples/matching/undo-match/): undo a match set (write).

See also [Differences with the documentation](/guides/documentation-differences/#matching).
