# Create pay run

> **This example changes data in Twinfield.** Only run it on a test administration.

Creates a pay run and selects the open invoices (CreateRun2 on payandcollect.asmx). A payment run (pay type `SEPANLCT`) selects purchase invoices, a direct debit run (`SEPANLDD`) sales invoices. The pay types of a company are in the Finder (type `PAY`).

- `PayRunInput::create()` takes the pay type, the pay date and the period to post in. The other settings, such as the method and the bank, default to the settings of the pay type.
- Filters: `invoiceNumber()`, `dimensions()` (a range of suppliers or customers), `maxDueDate()` and `currency()`.
- The new run is in phase Select. Change it with modify(), or delete it with the [Finish pay run](../finish-pay-run/) example.

## Code

```php
$twinfield = Example::twinfield();

// E.g. `SEPANLCT 123`: a payment run for purchase invoice 123. Without an invoice number, every
// open invoice that is due within 14 days is selected.
[, $payType, $invoiceNumber] = array_pad($argv ?? [], 3, null);

if ($payType === null) {
    echo 'Usage: php example.php <pay type> [<invoice number>]', PHP_EOL;

    exit(0);
}

$payDate = new DateTimeImmutable('tomorrow');
$input = PayRunInput::create($payType, $payDate, $payDate->format('Y/m'))
    ->method(PayRunMethod::Summarise)
    ->maxDueDate($payDate->modify('+14 days'));

if ($invoiceNumber !== null) {
    $input = $input->invoiceNumber($invoiceNumber);
}

try {
    $created = $twinfield->payAndCollect()->create($input);
} catch (PayAndCollectException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

foreach ($created->messages as $message) {
    echo $message->type->name, ': ', $message->text, PHP_EOL;
}

$run = $created->run;

echo 'Created ', $run->payType, ' ', $run->id, ' with ', count($run->summaries), ' summaries', PHP_EOL;

foreach ($run->summaries as $summary) {
    echo '  ', $summary->relation, ' ', $summary->relationName, ': ', $summary->currency, ' ', $summary->value(),
        PHP_EOL;
}
```

## Run

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/pay-and-collect/create-pay-run/example.php SEPANLCT 123
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

The output below follows the WSDL; it was not run live yet.

```text
Created SEPANLCT 12 with 1 summaries
  2001 Example Supplier B.V.: EUR 6050.00
```
