# List pay runs

Lists the payment and direct debit runs of a company (GetRuns on payandcollect.asmx), without their summaries. Read a run with its summaries and invoices with the [Get pay run](../get-pay-run/) example.

- `list()` returns a `PayRunOverview` per run: pay type, ID, run type (`Payment` or `DirectDebit`), phase and status, bank, pay date and period.
- A run stays in the list until it is finished, see the [Finish pay run](../finish-pay-run/) example.

## Code

```php
$twinfield = Example::twinfield();

$runs = $twinfield->payAndCollect()->list();

if ($runs === []) {
    echo 'No pay runs in company ', $twinfield->companyCode(), '.', PHP_EOL;

    exit;
}

foreach ($runs as $run) {
    echo $run->payType, ' ', $run->id, ': ', $run->runType->name, ', ', $run->phase->name, ' ', $run->status->name,
        ', pay date ', $run->payDate->format('Y-m-d'), ', period ', $run->period, ', bank ', $run->bank, PHP_EOL;
}
```

## Run

```sh
TWINFIELD_COMPANY=1002_3 php examples/pay-and-collect/list-pay-runs/example.php
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

Output of a company without runs, run live on 10 November 2026:

```text
No pay runs in company 1002_3.
```
