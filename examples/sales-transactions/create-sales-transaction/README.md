# Create sales transaction

Books an invoice from your own software as sales transaction in day book `VRK`, then deletes it again. Without `--save` it only shows the request. With `--save` it changes data in Twinfield.

Use a sales transaction when the invoice is already made in your own software: Twinfield only gets the booking, not an invoice. See [Sales invoice or sales transaction?](https://php-twinfield.solvebeam.com/knowledge-base/sales-invoice-or-sales-transaction/) for the difference with a sales invoice.

`SalesTransactionInput::invoice($dayBook, $customer, $invoiceNumber, $date)` starts the input. A sales transaction has:

- a **total line** with the customer (`dim2`) and the amount **including** VAT. The input calculates it from the other lines: `debit` for an invoice, `credit` for a credit note. Without `receivablesAccount()`, Twinfield uses the accounts receivable account of the customer;
- a **detail line** per `revenue()`: the revenue account (`dim1`) with the amount **without** VAT and a VAT code. For an invoice it is `credit`.

With a `VatCode` object, `revenue()` calculates the VAT with its percentage on the invoice date (`$vatCode->vatOn()`), so the total line matches what Twinfield calculates. With a VAT code as string, give the VAT amount too (`vatValue: '21.00'`). Amounts are `Amount` objects or numeric strings, never floats.

New transactions are provisional, so they can still be changed or deleted. Call `final()` on the input to post it at once. `create()` returns the `SalesTransaction` as Twinfield saved it, with the transaction number, the VAT lines Twinfield added and the open amount.

The example uses the first customer, the first profit and loss account and VAT code `VH` (or the first sales VAT code). In your own application you use your revenue account, e.g. `8000`.

## Code

```php
$twinfield = Example::twinfield();
$sales = $twinfield->salesTransactions();

// In your own application: your sales day book, customer, revenue account and VAT code.
$customer = Example::customerCodes($twinfield, 1)[0];
$revenue = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::salesVatCode($twinfield));
$date = new DateTimeImmutable('today');

// The invoice was made in your own software: book its amounts. With a VatCode, the VAT is
// calculated with its percentage on the invoice date, so the total line matches Twinfield.
$input = SalesTransactionInput::invoice('VRK', $customer, 'EXAMPLE-' . $date->format('Ymd'), $date)
    ->dueDate($date->modify('+30 days'))
    ->revenue($revenue, '100.00', vatCode: $vatCode, description: 'Example sale');

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to create it as provisional transaction and delete it again:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml((string) $twinfield->companyCode())), PHP_EOL;

    exit(0);
}

try {
    $transaction = $sales->create($input);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Created:  ', $transaction->dayBook(), ' ', $transaction->number(), ' (', $transaction->header->status->name, ')', PHP_EOL;
echo 'Invoice:  ', $transaction->invoiceNumber, ', due ', $transaction->dueDate->format('Y-m-d'), PHP_EOL;
echo 'Customer: ', $transaction->customer()->code, ' ', $transaction->customer()->name, PHP_EOL;
echo 'Total:    ', $transaction->value(), ' including ', $transaction->total->vatTotal, ' VAT, open ',
    $transaction->total->openBaseValue, PHP_EOL;

foreach ($transaction->details as $line) {
    echo '  detail ', $line->account->code, ' ', $line->debitCredit->value, ' ', $line->amounts->value,
        ' ', $line->vatCode, ' ', $line->vatValue, PHP_EOL;
}

foreach ($transaction->vat as $line) {
    echo '  vat    ', $line->account->code, ' ', $line->debitCredit->value, ' ', $line->amounts->value, PHP_EOL;
}

// Clean up: delete the provisional example transaction again.
$sales->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted:  ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
```

## Run

```sh
php examples/sales-transactions/create-sales-transaction/example.php
```

```text
Dry run; add --save to create it as provisional transaction and delete it again:

<transaction destiny="temporary">
  <header>
    <office>1002</office>
    <code>VRK</code>
    <date>20261109</date>
    <duedate>20261209</duedate>
    <invoicenumber>EXAMPLE-20261109</invoicenumber>
  </header>
  <lines>
    <line type="total" id="1">
      <dim2>1000</dim2>
      <debitcredit>debit</debitcredit>
      <value>121.00</value>
    </line>
    <line type="detail" id="2">
      <dim1>40000</dim1>
      <debitcredit>credit</debitcredit>
      <value>100.00</value>
      <description>Example sale</description>
      <vatcode>VH</vatcode>
      <vatvalue>21.00</vatvalue>
    </line>
  </lines>
</transaction>
```

Add `--save` to send it. This creates a provisional transaction and deletes it again:

```sh
php examples/sales-transactions/create-sales-transaction/example.php --save
```
