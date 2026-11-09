# Credit note

Books a credit note from your own software as sales transaction, then deletes it again. Without `--save` it only shows the request. With `--save` it changes data in Twinfield.

`SalesTransactionInput::creditNote()` works like `invoice()`, with the same positive amounts. Only the sides turn around: `revenue()` adds **debit** lines, and the calculated total line is **credit**. `total()` is negative: the customer gets money back. On the result, `isCreditNote()` is true and `value()` is negative.

Use `line()` with a `DetailLineInput` for a transaction that mixes both sides, e.g. an invoice with a discount line: the total line takes the side of the sum.

## Code

```php
$twinfield = Example::twinfield();
$sales = $twinfield->salesTransactions();

$customer = Example::customerCodes($twinfield, 1)[0];
$revenue = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::salesVatCode($twinfield));
$date = new DateTimeImmutable('today');

// A credit note for part of an invoice: the same positive amounts as on the invoice. The
// credit note turns the sides around: debit revenue lines and a credit total line.
$input = SalesTransactionInput::creditNote('VRK', $customer, 'EXAMPLE-C-' . $date->format('Ymd'), $date)
    ->dueDate($date)
    ->description('Credit note for EXAMPLE-' . $date->format('Ymd'))
    ->revenue($revenue, '40.00', vatCode: $vatCode, description: 'Returned goods');

echo 'Total: ', $input->total(), ' (negative: the customer gets money back)', PHP_EOL;

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

echo 'Created: ', $transaction->dayBook(), ' ', $transaction->number(), ' ', $transaction->invoiceNumber,
    $transaction->isCreditNote() ? ' (credit note)' : '', ': ', $transaction->value(), PHP_EOL;

// Clean up: delete the provisional example transaction again.
$sales->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted: ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
```

## Run

```sh
php examples/sales-transactions/credit-note/example.php
```

```text
Total: -48.40 (negative: the customer gets money back)
Dry run; add --save to create it as provisional transaction and delete it again:

<transaction destiny="temporary">
  <header>
    <office>1002</office>
    <code>VRK</code>
    <date>20261109</date>
    <duedate>20261109</duedate>
    <invoicenumber>EXAMPLE-C-20261109</invoicenumber>
  </header>
  <lines>
    <line type="total" id="1">
      <dim2>1000</dim2>
      <debitcredit>credit</debitcredit>
      <value>48.40</value>
      <description>Credit note for EXAMPLE-20261109</description>
    </line>
    <line type="detail" id="2">
      <dim1>40000</dim1>
      <debitcredit>debit</debitcredit>
      <value>40.00</value>
      <description>Returned goods</description>
      <vatcode>VH</vatcode>
      <vatvalue>8.40</vatvalue>
    </line>
  </lines>
</transaction>
```

Add `--save` to send it. This creates a provisional transaction and deletes it again:

```sh
php examples/sales-transactions/credit-note/example.php --save
```
