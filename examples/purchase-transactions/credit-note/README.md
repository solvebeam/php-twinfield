# Credit note

Books a credit note of a supplier as purchase transaction, then deletes it again. Without `--save` it only shows the request. With `--save` it changes data in Twinfield.

`PurchaseTransactionInput::creditNote()` takes the same arguments as `invoice()`, and `expense()` the same positive amounts. The credit note turns the sides around: the cost lines are `credit` and the total line with the supplier is `debit`. `total()` is negative: the supplier owes you money.

Read back, `isCreditNote()` is true and `value()` is negative.

## Code

```php
$twinfield = Example::twinfield();
$purchases = $twinfield->purchaseTransactions();

$supplier = Example::supplierCode($twinfield);
$costs = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::purchaseVatCode($twinfield));
$date = new DateTimeImmutable('today');

// A credit note of the supplier: the same positive amounts as on an invoice. The credit note
// turns the sides around: credit cost lines and a debit total line.
$input = PurchaseTransactionInput::creditNote('INK', $supplier, 'EXAMPLE-C-' . $date->format('Ymd'), $date)
    ->dueDate($date)
    ->description('Credit note for EXAMPLE-' . $date->format('Ymd'))
    ->expense($costs, '40.00', vatCode: $vatCode, description: 'Returned goods');

echo 'Total: ', $input->total(), ' (negative: the supplier owes you money)', PHP_EOL;

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

echo 'Created: ', $transaction->dayBook(), ' ', $transaction->number(), ' ', $transaction->invoiceNumber,
    $transaction->isCreditNote() ? ' (credit note)' : '', ': ', $transaction->value(), PHP_EOL;

// Clean up: delete the provisional example transaction again.
$purchases->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted: ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
```

## Run

```sh
php examples/purchase-transactions/credit-note/example.php
```

```text
Total: -48.40 (negative: the supplier owes you money)
Dry run; add --save to create it as provisional transaction and delete it again:

<transaction destiny="temporary">
  <header>
    <office>1002</office>
    <code>INK</code>
    <date>20261109</date>
    <duedate>20261109</duedate>
    <invoicenumber>EXAMPLE-C-20261109</invoicenumber>
  </header>
  <lines>
    <line type="total" id="1">
      <dim2>2000</dim2>
      <debitcredit>debit</debitcredit>
      <value>48.40</value>
      <description>Credit note for EXAMPLE-20261109</description>
    </line>
    <line type="detail" id="2">
      <dim1>40000</dim1>
      <debitcredit>credit</debitcredit>
      <value>40.00</value>
      <description>Returned goods</description>
      <vatcode>IH</vatcode>
      <vatvalue>8.40</vatvalue>
    </line>
  </lines>
</transaction>
```

Add `--save` to send it:

```sh
php examples/purchase-transactions/credit-note/example.php --save
```
