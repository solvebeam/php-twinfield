---
title: Transactions
description: Read, create, update, post and delete bank, cash, sales, purchase and journal transactions, with exact amounts that always balance.
---

Transactions are the bookings in Twinfield: bank and cash statements, sales and purchase invoices, and journal entries. The SDK reads and writes them with the [XML web services](/guides/process-xml/). You work with typed objects and never write XML yourself.

| Transactions | Twinfield documentation | Resource | Status |
|---|---|---|---|
| Bank | [Bank transactions](https://developers.twinfield.com/documentation/api/transaction-data/bank-transactions/) | `$twinfield->bankTransactions()` | Read, create, update, save many, post, delete |
| Cash | [Cash transactions](https://developers.twinfield.com/documentation/api/transaction-data/cash-transactions/) | `$twinfield->cashTransactions()` | Read, create, update, save many, post, delete |
| Sales | [Sales transactions](https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/) | `$twinfield->salesTransactions()` | Read, create, update, save many, post, delete, change pay status, open invoices |
| Sales invoices | [Sales invoices](https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/) | `$twinfield->salesInvoices()` | Read, create, update, calculate, make final, invoice types; see [Sales invoices](#sales-invoices) |
| Journal | [Journal transactions](https://developers.twinfield.com/documentation/api/transaction-data/journal-transactions/) | `$twinfield->journalTransactions()` | Read, create, update, save many, post, delete; see [Journal transactions](#journal-transactions) |
| Purchase | [Purchase transactions](https://developers.twinfield.com/documentation/api/transaction-data/purchase-transactions/) | `$twinfield->purchaseTransactions()` | Read, create, update, save many, post, delete, change pay status, open invoices; see [Purchase transactions](#purchase-transactions) |
| Reversal transactions | [Reversal transactions](https://developers.twinfield.com/documentation/api/transaction-data/reversal-transactions/) | `$twinfield->reversalTransactions()` | Read, post final, post provisional, undo; see [Reversal transactions](#reversal-transactions) |
| Deleted transactions | [Deleted transactions](https://developers.twinfield.com/documentation/api/transaction-data/deleted-transactions/) | `$twinfield->deletedTransactions()` | List; see [Deleted transactions](#deleted-transactions) |
| Transaction blocked value | [Transaction blocked value](https://developers.twinfield.com/documentation/api/transaction-data/transaction-blocked-value/) | `$twinfield->transactionBlockedValues()` | Register, unregister; see [Blocked values](#blocked-values) |
| Electronic bank statements | [Electronic bank statements](https://developers.twinfield.com/documentation/api/transaction-data/electronic-bank-statements/) | `$twinfield->electronicBankStatements()` | Create, create many; see [Electronic bank statements](#electronic-bank-statements) |
| Electronic bank statements (read) | [Electronic bank statements](https://developers.twinfield.com/documentation/api/request-data/electronic-bank-statements/) (Request Data, BankStatementService) | `$twinfield->electronicBankStatements()` | List by statement date or by creation date; see [Reading bank statements](#reading-bank-statements) |
| Spread transactions | [Spread transactions](https://developers.twinfield.com/documentation/api/transaction-data/spread-transactions/) |  | Not supported yet |
| Time entry | [Time entry](https://developers.twinfield.com/documentation/api/transaction-data/time/) |  | Not supported yet |

The Twinfield documentation lists all fields of each transaction type. Where the SDK deviates from it, see [Differences with the documentation](/guides/documentation-differences/).

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

A transaction has a **header** and **lines**. The header has the day book (`code`, e.g. `BNK`), the date, the currency and, for bank and cash, the statement number with the opening and closing balance. For sales and purchase, it has the invoice number, the due date and the payment reference. Each line has a type:

| Line type | What it is | Bank and cash | Sales | Purchase |
|---|---|---|---|---|
| `total` | The balance side, including VAT | The bank or cash account, with the sum of all other lines | The customer, with what they owe | The supplier, with what you owe |
| `detail` | What the money is for, without VAT | A customer paying, a supplier being paid, a cost | The revenue | The costs |
| `vat` | VAT per VAT code (optional) | The VAT of detail lines with a VAT code | The VAT of the revenue | The VAT you can deduct |

Amounts you send are always positive; `debitcredit` gives the side. (Twinfield itself can return a negative amount, e.g. a discount line on an imported purchase invoice.) That is where most mistakes happen, because the side depends on the line type and on the kind of transaction:

| Kind | Money in (receipt) | Money out (payment) |
|---|---|---|
| Bank, cash | total `debit`, detail `credit` | total `credit`, detail `debit` |
| Sales | total `debit` (customer), detail `credit` (revenue) | credit note: the other way around |
| Purchase | total `credit` (supplier), detail `debit` (cost) | credit note: the other way around |
| Journal | no total line; every line has its own side, and the lines balance | |

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

## Sales transactions

A sales transaction books an invoice made in your own software. (To let Twinfield make the invoice, see [Sales invoice or sales transaction?](/knowledge-base/sales-invoice-or-sales-transaction/).) Start with `invoice()` or `creditNote()`, and add the revenue with `revenue()`:

```php
use SolveBeam\Twinfield\SalesTransaction\SalesTransactionInput;

$sales = $twinfield->salesTransactions();
$date = new DateTimeImmutable('2026-11-09');

$transaction = $sales->create(
    SalesTransactionInput::invoice('VRK', customer: '1000', invoiceNumber: '2026-0042', date: $date)
        ->dueDate($date->modify('+30 days'))
        ->paymentReference('2026-0042')
        ->revenue('8000', '100.00', vatCode: 'VH', vatValue: '21.00', description: 'Consultancy')
        ->revenue('8010', '50.00', vatCode: $twinfield->vatCodes()->get('VL'), costCenter: '00100', project: 'P0001'),
);

echo $transaction->invoiceNumber, ': ', $transaction->value(), ', open ', $transaction->total->openBaseValue, PHP_EOL;
```

- `revenue($account, $amount, …)` adds a detail line without VAT: `credit` for an invoice, `debit` for a credit note. Amounts stay positive.
- With a `VatCode` from `$twinfield->vatCodes()->get()`, the VAT is calculated with its percentage on the transaction date. With a VAT code as string, give `vatValue` too.
- The input calculates the **total line** with the customer: the sum including VAT, `debit` when the customer owes it. `total()` gives it before anything is sent; it is negative for a credit note.
- Without `receivablesAccount()`, Twinfield takes the accounts receivable account of the customer.
- `ignoreDuplicateInvoiceWarning()` turns off the warning for an invoice number that is already used. `excludeFromDirectDebit()` leaves the invoice out of the direct debit runs of Twinfield.

The SDK requires an invoice number, although the Twinfield documentation does not: without one, a payment can't be matched by invoice number.

`get()` returns a `SalesTransaction` with `invoiceNumber`, `dueDate`, `paymentReference` and a `total` with the customer (`dim2`, shortcut `customer()`), the payment status (`matchStatus`, `isPaid()`), the open amount (`openBaseValue`) and the payments it is matched with (`matches`).

`excludeFromCollection($dayBook, $number)` and `includeInCollection()` change whether Twinfield collects the invoice (`changepaystatus`).

`openInvoices()` searches unpaid invoices with Finder `IVT`, e.g. `$sales->openInvoices()->customer('1000')->get()`. Without `customer()` or `account()`, the results include open purchase invoices too. See [Open invoices](/examples/sales-transactions/open-invoices/).

## Purchase transactions

A purchase transaction books an invoice you received from a supplier. It is the mirror of a sales transaction: start with `invoice()` or `creditNote()`, with the invoice number of the supplier, and add the costs with `expense()`:

```php
use SolveBeam\Twinfield\PurchaseTransaction\PurchaseTransactionInput;

$purchases = $twinfield->purchaseTransactions();
$date = new DateTimeImmutable('2026-11-09');

$transaction = $purchases->create(
    PurchaseTransactionInput::invoice('INK', supplier: '2000', invoiceNumber: 'F-2026-118', date: $date)
        ->dueDate($date->modify('+14 days'))
        ->expense('7000', '100.00', vatCode: 'IH', vatValue: '21.00', description: 'Office supplies')
        ->expense('7010', '50.00', vatCode: $twinfield->vatCodes()->get('IL'), costCenter: '00100'),
);

echo $transaction->invoiceNumber, ': ', $transaction->value(), ', open ', $transaction->total->openBaseValue, PHP_EOL;
```

- `expense($account, $amount, …)` adds a detail line without VAT: `debit` for an invoice, `credit` for a credit note. Amounts stay positive. The account can also be a balance sheet account, e.g. for stock.
- The input calculates the **total line** with the supplier: the sum including VAT, `credit` when you owe it. `total()` gives it before anything is sent: positive for an invoice, negative for a credit note.
- Without `payablesAccount()`, Twinfield takes the accounts payable account of the supplier.
- `ignoreDuplicateInvoiceWarning()` turns off the warning for an invoice number that is already used. `excludeFromPaymentRuns()` leaves the invoice out of the payment runs of Twinfield.

The purchase day book is usually `INK` in Dutch companies and `PNV` in English ones.

`get()` returns a `PurchaseTransaction` with `invoiceNumber`, `dueDate` (null when not set), `paymentReference` and a `total` with the supplier (`dim2`, shortcut `supplier()`), the payment status (`matchStatus`, `isPaid()`), the open amount and the payments it is matched with. `value()` is positive for an invoice. The open amount (`openBaseValue`) is signed like the total line: negative for a purchase invoice.

`excludeFromPaymentRuns($dayBook, $number)` and `includeInPaymentRuns()` change whether Twinfield pays the invoice in its payment runs (`changepaystatus`).

`openInvoices()` searches unpaid invoices with Finder `IVT`, e.g. `$purchases->openInvoices()->supplier('2000')->get()`. Without `supplier()` or `account()`, the results include open sales invoices too. See [Open invoices](/examples/purchase-transactions/open-invoices/).

## Journal transactions

A journal transaction (memorial booking) has no total line: every line has its own side, and the debit lines must equal the credit lines. Use it for corrections, reclassifications, wages, accruals and the opening balance.

```php
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\JournalTransaction\JournalTransactionInput;

$journal = $twinfield->journalTransactions();

$transaction = $journal->create(
    JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-04'))
        ->debit('4008', '435.55')
        ->credit('1300', '435.55', customer: '1000', invoice: '11001770', description: 'Invoice paid'),
);

echo $transaction->number(), ': ', $transaction->debit(), ' = ', $transaction->credit(), PHP_EOL;
```

- `debit($account, $amount, …)` and `credit(…)` add a detail line, with the same named arguments as `receive()` and `pay()`: `customer`, `supplier` or `costCenter` (`dim2`), `project` (`dim3`), `invoice`, `description`, `vatCode` and `vatValue`. `line(DetailLineInput …)` adds one with full control.
- The input checks that the lines balance before anything is sent, and throws an `InvalidArgumentException` with the difference when they don't. `balance()` gives the difference: zero when it balances.
- A line with a VAT code needs the VAT amount, or a `VatCode` object to calculate it. Twinfield books the VAT on the VAT account, on the **same side** as the line, so the other lines must include it: a credit line of `1000.00` with `210.00` VAT needs `1210.00` on the debit side.
- `regime(Regime::Fiscal)` books the transaction in one regime (`Fiscal`, `Commercial` or `Economic`). Without it Twinfield books `Generic`: visible in all regimes. `$transaction->regime` has it.

Twinfield has several kinds of journal: memorial, wages, opening balance and year end. They are journal transactions in their own day book, e.g. `MEMO`, `SALARIS`, `BEGINBALANS` and `VJP`; the codes differ per company (Finder `TRS` lists them). An opening balance goes in period 0 and a year end booking in period 55: `->period(Period::of(2026, 0))`. Whether a period is open, and its name, comes from the PeriodService: `$twinfield->periods()->get($transaction->header->period)`, see [Periods](/guides/periods/).

`get()` returns a `JournalTransaction` with the `header`, the `regime`, the `details` and the `vat` lines; `debit()` and `credit()` are the sums of both sides.

Examples: [Read journal transaction](/examples/journal-transactions/read-journal-transaction/), [Create journal transaction](/examples/journal-transactions/create-journal-transaction/) and [Opening balance](/examples/journal-transactions/opening-balance/). How the live responses differ from the documentation: [Differences with the documentation](/guides/documentation-differences/#journal-transactions).

## Reversal transactions

A reversal transaction is a complete reverse of a journal transaction, e.g. to undo an accrual in the next period. Twinfield makes it itself, in the reversal day book of the journal (e.g. `MEMOTEGENBOEKING` for `MEMO`), and links it to the original.

```php
$reversals = $twinfield->reversalTransactions();

$reversal = $reversals->get('MEMO', 202600003);

if ($reversal->reversed === null) {
    $reversed = $reversals->postFinal('MEMO', 202600003);

    $transaction = $twinfield->journalTransactions()->get($reversed->dayBook, $reversed->number);
}
```

- `get($dayBook, $number)` returns a `Reversal`: the `original` transaction, the `reversed` transaction (or `null` when there is none) and `reverseInNextPeriod`. Both transactions are a `TransactionReference` (company, day book and number): read the transaction itself with `journalTransactions()`.
- `postFinal()` reverses a **final** journal transaction: Twinfield creates the reversed transaction and posts it as final. It returns the reversed transaction.
- `postProvisional()` only saves the reversal settings of a **provisional** journal transaction; `undo()` removes them again.
- Only journal transactions have a reversal. For a sales or purchase transaction, or a number that does not exist, Twinfield answers `Originele transactie VRK:202600012 is niet gevonden.`, thrown as `ProcessXmlException`. A deleted journal transaction is still found, without reversed transaction.
- The documentation has no way to set `reverseInNextPeriod`: the requests only have the original transaction.

Examples: [Read reversal](/examples/reversal-transactions/read-reversal/) and [Reverse journal transaction](/examples/reversal-transactions/reverse-journal-transaction/). How the live responses differ from the documentation: [Differences with the documentation](/guides/documentation-differences/#reversal-transactions).

## Sales invoices

A sales invoice is not a transaction: Twinfield makes the invoice (in the Classic invoicing module) from an invoice type, a customer and articles, calculates the amounts and VAT, and books the sales transaction when the invoice is final. Use it when Twinfield makes and sends the invoice; see [Sales invoice or sales transaction?](/knowledge-base/sales-invoice-or-sales-transaction/).

```php
use SolveBeam\Twinfield\SalesInvoice\PaymentMethod;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceInput;

$invoices = $twinfield->salesInvoices();

$input = SalesInvoiceInput::create('FACTUUR', customer: '1000')
    ->dueDate(new DateTimeImmutable('2026-12-09'))
    ->paymentMethod(PaymentMethod::Bank)
    ->article('510', quantity: '2')
    ->oneOff('Consultancy November', '950.00', vatCode: 'VH');

echo $invoices->calculate($input)->totals->valueInc, PHP_EOL; // nothing is saved

$invoice = $invoices->create($input);                          // concept, with a number
$invoice = $invoices->update($invoice->edit()->footerText('Thank you!'));
$invoice = $invoices->finalize('FACTUUR', $invoice->invoiceNumber);

$transaction = $invoices->salesTransaction($invoice);          // the booking, e.g. VRK 202600013
```

- `article($code, $quantity)` adds an article: Twinfield takes its price, VAT code and account. Give a price with `SalesInvoiceLineInput::article()->unitPriceExcl()` only when the price of the article may be changed.
- `oneOff($description, $price)` adds the one-off item `0`, which is in every company. Twinfield books it on the account of the invoice type; `account:` overrides that when the invoice type allows it.
- Prices are excluding or including VAT (`unitPriceExcl()` or `unitPriceInc()`): the invoice type decides which.
- New invoices are concept. `final()` on the input, or `finalize()`, makes an invoice final: it can't be changed anymore, and `$invoice->transaction` has the day book and number of the sales transaction.
- `calculate()` sends the invoice with `calculateonly="true"`: Twinfield checks and calculates it without saving.
- `withoutWarnings()` saves with `raisewarning="false"`; `period('2026/11', raiseWarning: false)` only suppresses the warning about a date outside the period.
- `invoiceTypes()` searches the invoice types with Finder `INV`.

`get($invoiceType, $invoiceNumber)` returns a `SalesInvoice` with the header, the `lines` with the amounts Twinfield calculated, the `vatLines` and the `totals`. There is no search for sales invoices, and the API can't delete them. Unpaid invoices are found through the sales transactions: [`openInvoices()`](#sales-transactions).

Examples: [Read sales invoice](/examples/sales-invoices/read-sales-invoice/), [Create sales invoice](/examples/sales-invoices/create-sales-invoice/), [Finalize sales invoice](/examples/sales-invoices/finalize-sales-invoice/) and [List invoice types](/examples/sales-invoices/list-invoice-types/). How the live responses differ from the documentation: [Differences with the documentation](/guides/documentation-differences/#sales-invoices).

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

Calculations use bcmath. Read models return amounts as `Amount` too, e.g. `$transaction->closeValue` or `$line->value`.

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

To match lines of transactions that already exist, e.g. an invoice with a payment, fully, partly or with a discount, and to undo a match, see the [matching guide](/guides/matching/).

## Reading

`get($dayBook, $number)` returns a `BankTransaction` or `CashTransaction` (for sales: a `SalesTransaction`, see [Sales transactions](#sales-transactions); for journal: a `JournalTransaction`, see [Journal transactions](#journal-transactions)):

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

## Deleted transactions

`delete($dayBook, $number, $reason)` removes a provisional transaction. Reading it afterwards with `get()` throws a `TransactionNotFoundException` with `deleted` true, and `find()` returns `null`. Twinfield remembers what was deleted, also when that happened in Twinfield itself, for all day books (bank, cash, sales, purchase, journal, …). `$twinfield->deletedTransactions()` reads that list, newest deletion first:

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

## Electronic bank statements

An electronic bank statement is a bank statement as the bank reports it: the bank account, the balances and the lines, without ledger accounts. Twinfield allocates the lines itself with the allocation rules of the bank book, as it does with a statement downloaded from the bank. A [bank transaction](#receive-and-pay), on the other hand, is already booked: you choose the account of each line.

```php
use SolveBeam\Twinfield\ElectronicBankStatement\ElectronicBankStatementInput;

$statement = $twinfield->electronicBankStatements()->create(
    ElectronicBankStatementInput::forIban('NL91ABNA0417164300')
        ->date(new DateTimeImmutable('2026-11-09'))
        ->number(2, startValue: '7.68', closeValue: '151.18')
        ->receive('151.00', 'Invoice 2026-0042', contraIban: 'NL20INGB0001234567', type: 'N100')
        ->pay('7.50', 'Bank costs', type: 'N999'),
);

foreach ($statement->lines as $line) {
    echo $line->description, ': ', $line->dimension(1)?->code, ' ', $line->dimension(2)?->code, PHP_EOL;
}
```

- Twinfield finds the bank book by its IBAN (`forIban()`), account number (`forAccount()`) or code (`forBankBook()`).
- Everything else is optional. Without a `date()` Twinfield uses today, without a `currency()` the currency of the bank book, and without a number the last statement number plus one. Without an opening balance it takes the closing balance of the previous statement, and without a closing balance it adds the lines to the opening balance. With both balances, the input checks that the lines add up.
- `receive()` is money received by the bank (`credit`), `pay()` money paid by the bank (`debit`). The amount is always more than zero. A line can have a `contraIban` or a `contraAccount` (BBAN, with `contraName`), a bank `type` (at most 4 characters), a `reference` (40), a `description` (500), an `endToEndId` and a `returnReason`.
- The result is an `ElectronicBankStatement` with what Twinfield filled in, and per line the dimensions it allocated the line to, e.g. dim1 `1300` and dim2 customer `1000`, or the suspense account.
- A statement can't be changed after it is created, so there is no update or delete.
- Duplicates: by default (`importduplicate="0"`) Twinfield does not import a statement it already has. Pass `importDuplicate: true` to `create()` or `createMany()` to import it anyway.
- `createMany([$input, …])` imports several statements in one request, like [`saveMany()`](#saving-many-transactions): it does not throw when some fail, and `failed()` has the errors per statement.

Not yet verified live: the shape of the response, and what Twinfield answers for a duplicate. See [`TODO.md`](https://github.com/solvebeam/php-twinfield/blob/main/TODO.md). Example: [Import electronic bank statement](/examples/electronic-bank-statements/import-electronic-bank-statement/).

### Reading bank statements

The BankStatementService gives the statements Twinfield has, imported with `create()` or downloaded from the bank by Twinfield itself, with the lines and how Twinfield allocated them.

```php
$statements = $twinfield->electronicBankStatements()->list(
    from: new DateTimeImmutable('2026-11-01'),
    to: new DateTimeImmutable('2026-11-30'),
);

foreach ($statements as $statement) {
    echo $statement->bankBook, ' ', $statement->number, ' ', $statement->date->format('Y-m-d'), PHP_EOL;

    foreach ($statement->lines as $line) {
        foreach ($line->allocations as $allocation) {
            echo '  ', $line->amount, ' ', $allocation->type->value, ' ', $allocation->dimension(1), PHP_EOL;
        }
    }
}
```

- `list($from, $to)` gives the statements dated in that period, both days included; only the date counts. `listByCreationDate(from: …, to: …)` gives the statements imported in that period, with the time as given; one of the two is enough.
- Posted statements are included by default; `includePosted: false` leaves them out. Twinfield's own default is `false`, but statements that Twinfield processes automatically are posted right away, so you would see nothing.
- A `BankStatement` has `openingBalance`, `closingBalance`, `lines` and `transactionNumber`: the number of the bank transaction it is posted as, or null (`isPosted()`).
- A `BankStatementLine` has a signed `amount` (negative for money paid), the other party, `recognition` (how Twinfield recognised the line), the conditions of the allocation rule (`allocationMethods`) and the `allocations`.
- A `BankStatementAllocation` has a `type` (`GeneralLedger`, `Customer`, `Supplier`, `Suspense`, …), `dimensions` (positions 1 to 6), the `debitCredit` of the contra booking, `vatLines` and `matchings`: the transaction lines it is matched with, e.g. `INK 202601319` line 1. Money received is booked credit, so `signedAmount()` of the allocations of a line add up to the line amount.
- Twinfield has no paging. A long period with many statements can time out; ask for a month or less at a time.

Not the same as a [bank transaction](#receive-and-pay): read the booked transaction with `bankTransactions()` or [browse data](/guides/browse-data/). Example: [List electronic bank statements](/examples/electronic-bank-statements/list-electronic-bank-statements/).

## Blocked values

A blocked value is an amount associated with one line of a transaction, through the [TransactionBlockedValueService](https://developers.twinfield.com/documentation/api/transaction-data/transaction-blocked-value/). It works for every day book: the documentation example uses a memorial transaction (`MEMO`).

```php
$blocked = $twinfield->transactionBlockedValues();

$invoice = $twinfield->purchaseTransactions()->get('INK', 202600021);

$blocked->register('INK', $invoice->number(), $invoice->total->id, '50.00');
$blocked->unregister('INK', $invoice->number(), $invoice->total->id);
```

- The line is the `id` of a line of the transaction: `$transaction->total->id` for the total line, `$line->id` for a detail line.
- The value is an `Amount` or a numeric string with at most two decimals.
- Both commands return nothing. When Twinfield refuses one, you get a `CommandQueryFaultException` with `errorCode` `BlockedValueCannotBeRegistered` or `BlockedValueCannotBeUnregistered` (`TransactionBlockedValueServiceFaultCode`).
- The service cannot read a blocked value back, and the transaction responses don't show it.

Not yet verified live: which lines and which transactions (provisional or final) accept a blocked value, and whether it may be negative or more than the open amount. See [`TODO.md`](https://github.com/solvebeam/php-twinfield/blob/main/TODO.md).

## Saving many transactions

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
| `TransactionNotFoundException` | `get()` or `update()` of a transaction or day book that does not exist, or of a deleted transaction (`deleted` is true). Has `dayBook`, `number` and Twinfield's `reason`. |
| `ProcessXmlException` | Twinfield refused the request, e.g. an unknown account, or deleting a final transaction. Has the messages per element. |
| `UnexpectedResponseException` | Twinfield answered with something the SDK does not expect. |

## Examples

- [Read bank transaction](/examples/bank-transactions/read-bank-transaction/), [create](/examples/bank-transactions/create-bank-transaction/), [update](/examples/bank-transactions/update-bank-transaction/), [delete](/examples/bank-transactions/delete-bank-transaction/) and [post](/examples/bank-transactions/post-bank-transaction/).
- [Import bank statement](/examples/bank-transactions/import-bank-statement/): a CSV statement to one transaction, with rules per line and a balance check.
- [Bank costs with VAT](/examples/bank-transactions/bank-costs-with-vat/): split an amount including VAT, exactly.
- [Import electronic bank statement](/examples/electronic-bank-statements/import-electronic-bank-statement/): the same CSV statement, but Twinfield allocates the lines.
- [List electronic bank statements](/examples/electronic-bank-statements/list-electronic-bank-statements/): the statements of the last days, with their allocations and matchings.
- [Register blocked value](/examples/transaction-blocked-values/register-blocked-value/) and [unregister](/examples/transaction-blocked-values/unregister-blocked-value/).
- [Split payment over invoices](/examples/bank-transactions/split-payment-over-invoices/): one payment for several open invoices.
- [Bulk import with errors](/examples/bank-transactions/bulk-import-with-errors/): `saveMany()` and its result.
- [Daily cash register](/examples/cash-transactions/daily-cash-register/): sales per VAT rate, expenses and the cash difference of one day.
- [List deleted transactions](/examples/deleted-transactions/list-deleted-transactions/): what was deleted in the last days, by whom and why.
- [Read sales transaction](/examples/sales-transactions/read-sales-transaction/), [create](/examples/sales-transactions/create-sales-transaction/) and [credit note](/examples/sales-transactions/credit-note/).
- [Open invoices](/examples/sales-transactions/open-invoices/): unpaid invoices of a customer.
- [Read purchase transaction](/examples/purchase-transactions/read-purchase-transaction/), [create](/examples/purchase-transactions/create-purchase-transaction/) and [credit note](/examples/purchase-transactions/credit-note/).
- [Open invoices](/examples/purchase-transactions/open-invoices/): unpaid invoices of a supplier.
- [Read journal transaction](/examples/journal-transactions/read-journal-transaction/), [create](/examples/journal-transactions/create-journal-transaction/) and [opening balance](/examples/journal-transactions/opening-balance/).
- [Read reversal](/examples/reversal-transactions/read-reversal/) and [reverse journal transaction](/examples/reversal-transactions/reverse-journal-transaction/).
