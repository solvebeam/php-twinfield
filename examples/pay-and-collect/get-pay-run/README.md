# Get pay run

Reads a pay run with its summaries and invoices (GetRunInfo on payandcollect.asmx). Find the pay type and ID with the [List pay runs](../list-pay-runs/) example.

- A summary is a payment (or collection) to one relation in one currency, with the bank account of the relation.
- A detail is an invoice in the summary: the transaction line, the due date and the value, which modify() can lower.

## Code

```php
$twinfield = Example::twinfield();

// E.g. `SEPANLCT 12`: the pay type and the ID of the run, see the list-pay-runs example.
[, $payType, $id] = array_pad($argv ?? [], 3, null);

if ($payType === null || $id === null) {
    echo 'Usage: php example.php <pay type> <run ID>', PHP_EOL;

    exit(0);
}

$run = $twinfield->payAndCollect()->get($payType, (int) $id);

echo $run->payType, ' ', $run->id, ': ', $run->runType->name, ', ', $run->phase->name, ' ', $run->status->name,
    ', touched ', $run->version->touched, PHP_EOL;

foreach ($run->summaries as $summary) {
    echo PHP_EOL, 'Summary ', $summary->id, ': ', $summary->relation, ' ', $summary->relationName, ', ',
        $summary->currency, ' ', $summary->value(), $summary->included ? '' : ' (excluded)',
        ', IBAN ', $summary->relationBank->iban ?? 'none', PHP_EOL;

    foreach ($summary->details as $detail) {
        echo '  ', $detail->id, ': invoice ', $detail->invoiceNumber, ' (', $detail->transaction, ' ',
            $detail->transactionNumber, '), due ', $detail->invoiceDueDate->format('Y-m-d'), ', ', $detail->value,
            ' of ', $detail->originalValue, $detail->included ? '' : ' (excluded)', PHP_EOL;
    }
}
```

## Run

```sh
php examples/pay-and-collect/get-pay-run/example.php SEPANLCT 12
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

The output below follows the WSDL; it was not run live yet.

```text
SEPANLCT 12: Payment, Select Done, touched 2

Summary 1: 2001 Example Supplier B.V., EUR 6171.00, IBAN NL02ABNA0123456789
  1: invoice 123 (INK 202600012), due 2026-10-31, 6050.00 of 6050.00
  2: invoice 124 (INK 202600013), due 2026-11-14, 121.00 of 121.00
```
