---
title: Transactions
description: Read, create, update, post and delete bank and cash transactions, with exact amounts and a total line that always balances.
---

Transactions are the bookings in Twinfield: bank and cash statements, sales and purchase invoices, and journal entries. The SDK reads and writes them with the [XML web services](/guides/process-xml/). You work with typed objects and never write XML yourself.

| Transactions | Resource | Status |
|---|---|---|
| Bank | `$twinfield->bankTransactions()` | Read, create, update, save many, post, delete |
| Cash | `$twinfield->cashTransactions()` | Read, create, update, save many, post, delete |
| Sales, purchase, journal | – | Not yet; use [`processXml()`](/guides/process-xml/), see [Create sales transaction](/examples/process-xml/create-sales-transaction/) |

Sales transaction or sales invoice? See [Sales invoice or sales transaction?](/knowledge-base/sales-invoice-or-sales-transaction/) in the Twinfield knowledge base.

Transactions belong to a company, so the client needs one: `Twinfield::create($accessTokenProvider)->company('1000')`.

## At a glance

```php
use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;

$bank = $twinfield->bankTransactions();

// Read
$transaction = $bank->get('BNK', 202600045);   // or find(): null when it does not exist

// Create: one bank statement
$created = $bank->create(
    BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
        ->statement(245, startValue: '1000.00')
        ->receive('1300', '121.00', customer: '1000', invoice: '2026-0042', description: 'Invoice 2026-0042')
        ->pay('1600', '50.00', supplier: '2000', invoice: 'INV-778')
        ->pay('4500', '10.00', vatCode: 'IH', vatValue: '2.10', description: 'Bank costs'),
);

// Update, post, delete: only while the transaction is provisional
$bank->update($created->edit()->description('Statement 245'));
$bank->post('BNK', $created->number());
$bank->delete('BNK', $created->number(), 'Imported twice');

// Several transactions in one request
$result = $bank->saveMany($monday, $tuesday, $wednesday);
```

## How a transaction is built

A transaction has a **header** and **lines**. The header has the day book (`code`, e.g. `BNK`), the date, the currency and, for bank and cash, the statement number with the opening and closing balance. Each line has a type:

| Line type | What it is | Bank and cash |
|---|---|---|
| `total` | The balance side, including VAT | The bank or cash account, with the sum of all other lines |
| `detail` | What the money is for, without VAT | A customer paying, a supplier being paid, a cost |
| `vat` | VAT per VAT code (optional) | The VAT of detail lines with a VAT code |

Amounts are always positive; `debitcredit` gives the side. That is where most mistakes happen, because the side depends on the line type and on the kind of transaction:

| Kind | Money in (receipt) | Money out (payment) |
|---|---|---|
| Bank, cash | total `debit`, detail `credit` | total `credit`, detail `debit` |
| Sales | total `debit` (customer), detail `credit` (revenue) | credit note: the other way around |
| Purchase | total `credit` (supplier), detail `debit` (cost) | credit note: the other way around |
| Journal | no total line; every line has its own side | |

## receive() and pay()

With bank and cash transactions you never write `debit` or `credit` yourself:

- `receive($account, $amount, …)`: money comes in, a credit detail line;
- `pay($account, $amount, …)`: money goes out, a debit detail line.

Both take the details of the line as named arguments: `customer`, `supplier` or `costCenter` (`dim2`, only one of them), `project` (`dim3`), `invoice`, `description`, `vatCode` and `vatValue`.

The input calculates the **total line** on the bank or cash account: the sum of all lines including VAT, `debit` when more came in than went out. With an opening balance in `statement()`, it also calculates the **closing balance**. So the transaction always balances, and `balance()` and `closeValue()` tell you the result before anything is sent:

```php
$input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
    ->statement(245, startValue: '1000.00')
    ->receive('1300', '121.00', customer: '1000')
    ->pay('4500', '10.00', vatCode: 'IH', vatValue: '2.10');

echo $input->balance(), PHP_EOL;      // 108.90
echo $input->closeValue(), PHP_EOL;   // 1108.90
```

For full control over a line, build it yourself with `DetailLineInput` and add it with `line()`. It has every field of a detail line, such as `dueDate()`, `lineDate()`, `rate()`, `destinationCompany()` and `performance()`:

```php
use SolveBeam\Twinfield\Transaction\DetailLineInput;

$input = $input->line(
    DetailLineInput::credit('1300', '121.00')
        ->customer('1000')
        ->invoice('2026-0042')
        ->comment('Paid after reminder'),
);
```

The bank account of the total line comes from `create($dayBook, $account, $date)`. For bank transactions, `BankTransactionInput::forBankBook($bankBook, $date)` takes the code and the ledger account of a bank book from `$twinfield->bankBooks()->get('BNK')`. For cash transactions, `CashTransactionInput::forCashBook($cashBook, $date)` does the same with a cash book from `$twinfield->cashBooks()->get('KAS')`.

## Amounts

Amounts are `Amount` objects: exact numeric strings with two decimals, such as `121.00`. No floats (`0.1 + 0.2` is not `0.3`), and no cents. Methods that take an amount accept an `Amount` or a numeric string:

```php
use SolveBeam\Twinfield\Transaction\Amount;

$amount = Amount::of('121');            // 121.00
$amount->plus('0.1')->plus('0.2');      // 121.30, exactly
$amount->negate()->absolute();          // 121.00
Amount::sum(['10.00', '2.10']);         // 12.10

Amount::of('1,50');                     // InvalidArgumentException
```

Calculations use bcmath. Read models return amounts as `Amount` too, e.g. `$transaction->closeValue` or `$line->amounts->value`.

## VAT

A bank or cash line with a VAT code needs the VAT amount: `pay('4500', '10.00', vatCode: 'IH', vatValue: '2.10')`. The total line includes it, so without it the transaction would not balance; the input throws an `InvalidArgumentException` before anything is sent.

Twinfield checks the VAT against its own calculation and refuses the transaction when it differs. `autoBalanceVat()` lets Twinfield round differences of at most two cents. A bank statement usually has the amount including VAT: see [Bank costs with VAT](/examples/bank-transactions/bank-costs-with-vat/) for splitting it exactly.

VAT lines (`vatLine(VatLineInput::debit('IH', '2.10'))`) are optional. When you add them, the total uses their amounts instead of the VAT amounts of the detail lines.

For ICT VAT codes (intra-community supplies), add the performance details: `->performance(Performance::services($date)->country('DE'))`.

## Provisional and final

New transactions are **provisional** (`destiny="temporary"`). You can still change, post or delete them. Call `final()` on the input to save a transaction as final at once.

- `post($dayBook, $number)` makes a provisional transaction final.
- `delete($dayBook, $number, $reason)` deletes a provisional transaction; Twinfield keeps the reason.
- A final transaction can no longer be changed or deleted: correct it with a new transaction.

`$transaction->header->status` is `TransactionStatus::Provisional` or `TransactionStatus::Final`.

## Matching

A line with a customer or supplier and an invoice number can be matched by Twinfield itself. To match it with specific lines of other transactions, e.g. line 1 of sales invoice `VRK` 202600012, add a `MatchSetInput` to the detail line:

```php
use SolveBeam\Twinfield\Transaction\DetailLineInput;
use SolveBeam\Twinfield\Transaction\MatchSetInput;

$input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
    ->line(
        DetailLineInput::credit('1300', '435.55')
            ->customer('1000')
            ->invoice('2026-0042')
            ->match(
                MatchSetInput::create(new DateTimeImmutable('2026-11-03'))
                    ->line('VRK', 202600012, 1, '435.55'),
            ),
    );
```

The match value of the set is the sum of its lines. `withMatches()` replaces the sets of a line. `edit()` leaves the match sets of a transaction out, because those lines are already matched.

## Reading

`get($dayBook, $number)` returns a `BankTransaction` or `CashTransaction`:

- `header`: company, day book, number, status, period, date, currency, origin, user and dates;
- `statementNumber`, `startValue`, `closeValue`;
- `total`: the `TotalLine` on the bank or cash account;
- `details`: `DetailLine`s, with `dim2`/`dim3` as `DimensionReference` (code, type and the name Twinfield adds), invoice number, VAT, and the payment status: `matchStatus`, open amounts and `matches`;
- `vat`: `VatLine`s.

Optional values are `null` when Twinfield leaves them out. Anything that does not match what Twinfield documents, such as an amount that is not a number or an unknown line type, throws an `UnexpectedResponseException` with the path, e.g. `transaction/lines/line[2]/value is "121,00", expected an amount.`

## Changing

Start from `$transaction->edit()`: an input with all values of the transaction, including its number. Change what you need, and pass it to `update()`:

```php
$bank->update(
    $transaction->edit()
        ->mapLines(fn (DetailLineInput $line, int $index) => $index === 0 ? $line->description('Paid') : $line)
        ->pay('4500', '2.50', description: 'Extra bank costs'),
);
```

`update()` reads the transaction first, so it throws a `TransactionNotFoundException` when it does not exist. `save()` sends the input without that check: it creates the transaction when it has no number, and updates it otherwise.

## Saving many transactions

## Deleted transactions

`delete($dayBook, $number, $reason)` removes a provisional transaction. Twinfield remembers what was deleted, also when that happened in Twinfield itself, for all day books (bank, cash, sales, purchase, journal, …). `$twinfield->deletedTransactions()` reads that list, newest deletion first:

```php
$deleted = $twinfield->deletedTransactions()->list(
    dayBook: 'BNK',
    from: new DateTimeImmutable('2026-11-01'),
    to: new DateTimeImmutable('2026-11-09'),
);

foreach ($deleted as $transaction) {
    echo $transaction->dayBook, ' ', $transaction->number, ' deleted by ', $transaction->user, ': ', $transaction->reason, PHP_EOL;
}
```

Each `DeletedTransaction` has `dayBook`, `number`, `transactionDate`, `deletionDate` (with time), `user` (the user code) and `reason`. All filters are optional:

- `dayBook`: one day book, in any case. An unknown code gives a `CommandQueryFaultException` with `errorCode` `InvalidDaybook`.
- `from` and `to`: the days of deletion. Only the date counts, not the time, and both days are included: `from` and `to` on the same day give everything deleted on that day. `from` after `to` throws an `InvalidArgumentException`.

Use it to keep a copy of the transactions in sync: ask for what was deleted since the last sync, and remove those transactions on your side. Without filters you get every deleted transaction of the company, which can be thousands (Twinfield has no paging), so filter on a date when you can.

`saveMany(...$inputs)` saves several transactions in one request. Twinfield saves the valid ones and refuses the others, so it does not throw: it returns a `SaveManyResult` with, per input in the same order, a `SavedTransaction` or a `FailedTransaction`:

```php
$result = $bank->saveMany(...$inputs);

foreach ($result->saved() as $saved) {
    echo 'Saved ', $saved->transaction->number(), PHP_EOL;
}

foreach ($result->failed() as $failed) {
    echo 'Failed #', $failed->index, ': ', $failed->message(), PHP_EOL;
}
```

## Warnings

By default Twinfield returns warnings, e.g. when the date is outside the period. `raiseWarnings(false)` turns them off for the whole transaction; `ignoreDateWarning()` only for the date. With `saveMany()`, warnings are in `$saved->warnings` and `$failed->warnings`.

## Errors

| Exception | When |
|---|---|
| `InvalidArgumentException` | Invalid input, before anything is sent: an invalid amount, a description over 40 characters, a VAT code without VAT amount, a line with both a customer and a supplier. |
| `TransactionNotFoundException` | `get()` or `update()` of a transaction or day book that does not exist. Has `dayBook`, `number` and Twinfield's `reason`. |
| `ProcessXmlException` | Twinfield refused the request, e.g. an unknown account, or deleting a final transaction. Has the messages per element. |
| `UnexpectedResponseException` | Twinfield answered with something the SDK does not expect. |

## Examples

- [Read bank transaction](/examples/bank-transactions/read-bank-transaction/), [create](/examples/bank-transactions/create-bank-transaction/), [update](/examples/bank-transactions/update-bank-transaction/), [delete](/examples/bank-transactions/delete-bank-transaction/) and [post](/examples/bank-transactions/post-bank-transaction/).
- [Import bank statement](/examples/bank-transactions/import-bank-statement/): a CSV statement to one transaction, with rules per line and a balance check.
- [Bank costs with VAT](/examples/bank-transactions/bank-costs-with-vat/): split an amount including VAT, exactly.
- [Split payment over invoices](/examples/bank-transactions/split-payment-over-invoices/): one payment for several open invoices.
- [Bulk import with errors](/examples/bank-transactions/bulk-import-with-errors/): `saveMany()` and its result.
- [Daily cash register](/examples/cash-transactions/daily-cash-register/): sales per VAT rate, expenses and the cash difference of one day.

See the Twinfield documentation of [bank transactions](https://developers.twinfield.com/documentation/api/transaction-data/bank-transactions/) and [cash transactions](https://developers.twinfield.com/documentation/api/transaction-data/cash-transactions/) for all fields.
- [List deleted transactions](/examples/deleted-transactions/list-deleted-transactions/): what was deleted in the last days, by whom and why.
