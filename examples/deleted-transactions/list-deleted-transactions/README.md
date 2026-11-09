# List deleted transactions

Shows the transactions that were deleted in the last 30 days, newest first: day book, number, date of the transaction, when and by whom it was deleted, and why.

Deleted transactions come from the DeletedTransactionsService, a [Command & Query web service](https://developers.twinfield.com/documentation/api/types/command-and-query-webservices), so the client needs a company (`company('1000')`); `Example::twinfield()` uses your default company.

- `deletedTransactions()->list()`: all deleted transactions of the company, of all day books (bank, cash, sales, purchase, journal, …), as `DeletedTransaction` objects with `dayBook`, `number`, `transactionDate`, `deletionDate`, `user` and `reason`.
- `list(dayBook: 'BNK')`: only one day book. An unknown day book gives a `CommandQueryFaultException` with `errorCode` `InvalidDaybook` (see `DeletedTransactionsServiceFaultCode`).
- `list(from: …, to: …)`: deleted between two days. Only the date counts, not the time, and both days are included.

This is useful to keep a copy of the transactions in sync: transactions deleted in Twinfield itself show up here too. Without filters you get every deleted transaction of the company, which can be thousands: Twinfield has no paging, so filter on a date when you can.

## Code

```php
$twinfield = Example::twinfield();

$days = (int) ($argv[1] ?? 30);
$dayBook = $argv[2] ?? null;
$from = new DateTimeImmutable(sprintf('-%d days', $days));

$transactions = $twinfield->deletedTransactions()->list(dayBook: $dayBook, from: $from);

echo 'Company ', $twinfield->companyCode(), PHP_EOL;
echo count($transactions), ' transaction(s) deleted since ', $from->format('Y-m-d');
echo $dayBook === null ? '' : ' in day book ' . $dayBook, PHP_EOL;

foreach ($transactions as $index => $transaction) {
    echo sprintf(
        '[%d/%d] %s %d (dated %s) deleted %s by %s: %s',
        $index + 1,
        count($transactions),
        $transaction->dayBook,
        $transaction->number,
        $transaction->transactionDate->format('Y-m-d'),
        $transaction->deletionDate->format('Y-m-d H:i'),
        $transaction->user,
        $transaction->reason,
    ), PHP_EOL;
}
```

## Run

```sh
php examples/deleted-transactions/list-deleted-transactions/example.php           # last 30 days
php examples/deleted-transactions/list-deleted-transactions/example.php 365 BNK   # last year, day book BNK
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
Company 1000
3 transaction(s) deleted since 2026-10-10
[1/3] MEMO 202600018 (dated 2026-11-03) deleted 2026-11-04 05:31 by JDOE: Test data
[2/3] INK 202600231 (dated 2026-10-28) deleted 2026-10-30 14:02 by JDOE: Entered twice
[3/3] BNK 202600045 (dated 2026-10-12) deleted 2026-10-12 16:47 by ADMIN: Wrong statement
```
