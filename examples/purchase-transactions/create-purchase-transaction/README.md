# Create purchase transaction

Books an invoice you received from a supplier as purchase transaction in day book `INK`, then deletes it again. Without `--save` it only shows the request. With `--save` it changes data in Twinfield.

`PurchaseTransactionInput::invoice($dayBook, $supplier, $invoiceNumber, $date)` starts the input, with the invoice number **of the supplier**. A purchase transaction has:

- a **total line** with the supplier (`dim2`) and the amount **including** VAT. The input calculates it from the other lines: `credit` for an invoice, `debit` for a credit note. Without `payablesAccount()`, Twinfield uses the accounts payable account of the supplier;
- a **detail line** per `expense()`: the cost account (`dim1`) with the amount **without** VAT and a VAT code. For an invoice it is `debit`.

With a `VatCode` object, `expense()` calculates the VAT with its percentage on the invoice date (`$vatCode->vatOn()`), so the total line matches what Twinfield calculates. With a VAT code as string, give the VAT amount too (`vatValue: '21.00'`). Amounts are `Amount` objects or numeric strings, never floats.

Two suppliers can use the same invoice number. Twinfield then warns; `ignoreDuplicateInvoiceWarning()` turns that off. `excludeFromPaymentRuns()` leaves the invoice out of the payment runs of Twinfield.

New transactions are provisional, so they can still be changed or deleted. Call `final()` on the input to post it at once. `create()` returns the `PurchaseTransaction` as Twinfield saved it, with the transaction number, the VAT lines Twinfield added and the open amount.

The example uses the first supplier, the first profit and loss account and VAT code `IH` (or the first purchase VAT code). In your own application you use your cost account, e.g. `7000`.

## Code

```php
$twinfield = Example::twinfield();
$purchases = $twinfield->purchaseTransactions();

// In your own application: your purchase day book, supplier, cost account and VAT code.
$supplier = Example::supplierCode($twinfield);
$costs = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::purchaseVatCode($twinfield));
$date = new DateTimeImmutable('today');

// Book a received invoice with the invoice number of the supplier. With a VatCode, the VAT is
// calculated with its percentage on the invoice date, so the total line matches Twinfield.
$input = PurchaseTransactionInput::invoice('INK', $supplier, 'EXAMPLE-' . $date->format('Ymd'), $date)
    ->dueDate($date->modify('+14 days'))
    ->expense($costs, '100.00', vatCode: $vatCode, description: 'Example purchase');

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to create it as provisional transaction and delete it again:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml((string) $twinfield->companyCode())), PHP_EOL;

    exit(0);
}

try {
    $transaction = $purchases->create($input);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Created:  ', $transaction->dayBook(), ' ', $transaction->number(), ' (', $transaction->header->status->name, ')', PHP_EOL;
echo 'Invoice:  ', $transaction->invoiceNumber, ', due ', $transaction->dueDate?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Supplier: ', $transaction->supplier()->code, ' ', $transaction->supplier()->name, PHP_EOL;
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
$purchases->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted:  ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
```

## Run

```sh
php examples/purchase-transactions/create-purchase-transaction/example.php
```

```text
Dry run; add --save to create it as provisional transaction and delete it again:

<transaction destiny="temporary">
  <header>
    <office>1002</office>
    <code>INK</code>
    <date>20261109</date>
    <duedate>20261123</duedate>
    <invoicenumber>EXAMPLE-20261109</invoicenumber>
  </header>
  <lines>
    <line type="total" id="1">
      <dim2>2000</dim2>
      <debitcredit>credit</debitcredit>
      <value>121.00</value>
    </line>
    <line type="detail" id="2">
      <dim1>40000</dim1>
      <debitcredit>debit</debitcredit>
      <value>100.00</value>
      <description>Example purchase</description>
      <vatcode>IH</vatcode>
      <vatvalue>21.00</vatvalue>
    </line>
  </lines>
</transaction>
```

Add `--save` to send it. This creates a provisional transaction and deletes it again:

```sh
php examples/purchase-transactions/create-purchase-transaction/example.php --save
```
