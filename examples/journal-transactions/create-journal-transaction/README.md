# Create journal transaction

Books a correction as journal transaction in day book `MEMO`, then deletes it again. Without `--save` it only shows the request. With `--save` it changes data in Twinfield.

`JournalTransactionInput::create($dayBook, $date)` starts the input. A journal transaction has no total line: every line has its own side.

- `debit($account, $amount, ...)` adds a debit detail line, `credit(...)` a credit one. Both take the same named arguments: `customer`, `supplier` or `costCenter` (`dim2`), `project` (`dim3`), `invoice`, `description`, `vatCode` and `vatValue`.
- The lines must balance: the sum of the debit lines equals the sum of the credit lines. `balance()` gives the difference, and the input throws an `InvalidArgumentException` before anything is sent when it is not zero.
- A line with a VAT code needs the VAT amount. Twinfield books the VAT on the VAT account, on the same side as the line, so the other lines include it. With a `VatCode` object instead of a code, the VAT is calculated with its percentage on the transaction date.

New transactions are provisional, so they can still be changed or deleted. Call `final()` on the input to post it at once. `regime(Regime::Fiscal)` books it in one regime only; without it Twinfield uses `Generic`.

The example uses the first two profit and loss accounts of the company.

## Code

```php
$twinfield = Example::twinfield();
$journal = $twinfield->journalTransactions();

// In your own application: your journal day book and accounts, e.g. 4500 and 4510.
[$from, $to] = Example::generalLedgerAccounts($twinfield, 'PNL', 2);
$date = new DateTimeImmutable('today');

// A correction: move costs that were booked on the wrong account. Every line has its own side
// and the lines must balance; the input checks that before anything is sent.
$input = JournalTransactionInput::create('MEMO', $date)
    ->freeText(1, 'Correction')
    ->debit($to, '125.00', description: 'Booked on the wrong account')
    ->credit($from, '125.00', description: 'Booked on the wrong account');

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to create it as provisional transaction and delete it again:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml((string) $twinfield->companyCode())), PHP_EOL;

    exit(0);
}

try {
    $transaction = $journal->create($input);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Created: ', $transaction->dayBook(), ' ', $transaction->number(), ' (', $transaction->header->status->name, ')',
    ', period ', $transaction->header->period, PHP_EOL;

foreach ($transaction->details as $line) {
    echo '  ', $line->id, ' ', $line->account->code, ' ', $line->debitCredit->value, ' ', $line->amounts->value,
        PHP_EOL;
}

// Clean up: delete the provisional example transaction again.
$journal->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted: ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
```

## Run

```sh
php examples/journal-transactions/create-journal-transaction/example.php
```

```text
Dry run; add --save to create it as provisional transaction and delete it again:

<transaction destiny="temporary">
  <header>
    <office>1002</office>
    <code>MEMO</code>
    <date>20261109</date>
    <freetext1>Correction</freetext1>
  </header>
  <lines>
    <line type="detail" id="1">
      <dim1>40005</dim1>
      <debitcredit>debit</debitcredit>
      <value>125.00</value>
      <description>Booked on the wrong account</description>
    </line>
    <line type="detail" id="2">
      <dim1>40000</dim1>
      <debitcredit>credit</debitcredit>
      <value>125.00</value>
      <description>Booked on the wrong account</description>
    </line>
  </lines>
</transaction>
```

Add `--save` to send it. This creates a provisional transaction and deletes it again:

```sh
php examples/journal-transactions/create-journal-transaction/example.php --save
```
