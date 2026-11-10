# Pay run lifecycle

> **This example changes data in Twinfield, and posting can't be undone.** Only run it on a test administration.

Takes a pay run from [Create pay run](../create-pay-run/) through the other steps of payandcollect.asmx and saves the payment file(s):

1. `modify()` (Modify): optionally excludes the summary of one relation.
2. `authorise()` (Authorize): freezes the run.
3. `post()` (Post): posts the payments to the in-transfer ledger and matches them with the invoices.
4. `createFiles()` (CreateFiles): creates the SEPA file(s). There is more than one when the bank limits the number of transactions per file.
5. `finish()` (Finish): ends the run.

Each step takes the version (`touched` and `modified`) of the step before and returns the new one, with the warnings of the step. Twinfield refuses a step with an outdated version.

## Code

```php
$twinfield = Example::twinfield();

// E.g. `SEPANLCT 12 2001`: authorises run 12 without the invoices of supplier 2001, posts it, saves
// the payment file(s) and ends the run. Posting can't be undone.
[, $payType, $id, $exclude] = array_pad($argv ?? [], 4, null);

if ($payType === null || $id === null) {
    echo 'Usage: php example.php <pay type> <run ID> [<relation to exclude>]', PHP_EOL;

    exit(0);
}

$payAndCollect = $twinfield->payAndCollect();
$run = $payAndCollect->get($payType, (int) $id);
$version = $run->version;

$print = static function (string $step, PayAndCollectMessage ...$messages): void {
    echo $step, PHP_EOL;

    foreach ($messages as $message) {
        echo '  ', $message->type->name, ': ', $message->text, PHP_EOL;
    }
};

try {
    if ($exclude !== null) {
        $modification = PayRunModification::of($run);

        foreach ($run->summaries as $summary) {
            if ($summary->relation === $exclude) {
                $modification = $modification->excludeSummary($summary->id);
            }
        }

        $version = $payAndCollect->modify($modification, $version);
        $print('Modified: excluded ' . $exclude, ...$version->messages);
    }

    $version = $payAndCollect->authorise($run, $version);
    $print('Authorised', ...$version->messages);

    $version = $payAndCollect->post($run, $version);
    $print('Posted to the in-transfer ledger', ...$version->messages);

    $files = $payAndCollect->createFiles($run, $version);
    $print('Created ' . count($files->files) . ' file(s)', ...$files->version->messages);

    foreach ($files->files as $index => $contents) {
        $file = sprintf('%s/pay-run-%s-%d-%d.xml', sys_get_temp_dir(), $run->payType, $run->id, $index + 1);
        file_put_contents($file, $contents);

        echo '  Saved to ', $file, ' (', strlen($contents), ' bytes)', PHP_EOL;
    }

    $print('Finished', ...$payAndCollect->finish($run, $files->version));
} catch (PayAndCollectException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}
```

## Run

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/pay-and-collect/pay-run-lifecycle/example.php SEPANLCT 12
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

The output below follows the WSDL; it was not run live yet.

```text
Authorised
Posted to the in-transfer ledger
Created 1 file(s)
  Saved to /tmp/pay-run-SEPANLCT-12-1.xml (2817 bytes)
Finished
```
