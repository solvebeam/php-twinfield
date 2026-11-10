# Finish pay run

> **This example changes data in Twinfield.** Only run it on a test administration.

Finishes a pay run (Finish on payandcollect.asmx). Before the run is posted, this deletes it and the invoices are available for a new run. After the files were created, it ends the run. Either way, all run data is removed from Twinfield.

- A run that is posted, but has no files yet, can't be finished.

## Code

```php
$twinfield = Example::twinfield();

// E.g. `SEPANLCT 12`: deletes run 12 when it is not posted yet, or ends it after its files were created.
[, $payType, $id] = array_pad($argv ?? [], 3, null);

if ($payType === null || $id === null) {
    echo 'Usage: php example.php <pay type> <run ID>', PHP_EOL;

    exit(0);
}

$payAndCollect = $twinfield->payAndCollect();
$run = $payAndCollect->get($payType, (int) $id);

try {
    $payAndCollect->finish($run);
} catch (PayAndCollectException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Finished ', $run->payType, ' ', $run->id, ' (was ', $run->phase->name, ' ', $run->status->name, ')', PHP_EOL;
```

## Run

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/pay-and-collect/finish-pay-run/example.php SEPANLCT 12
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

The output below follows the WSDL; it was not run live yet.

```text
Finished SEPANLCT 12 (was Select Done)
```
