# List electronic bank statements

Shows the bank statements of the last 7 days with their balances, whether they are posted, and per line the amount, the other party and how Twinfield allocated it.

Statements come from the BankStatementService, a [Command & Query web service](https://developers.twinfield.com/documentation/api/types/command-and-query-webservices), so the client needs a company (`company('1000')`); `Example::twinfield()` uses your default company. You get every statement of the company: the ones you imported with `create()` and the ones Twinfield downloaded from the bank itself.

- `electronicBankStatements()->list($from, $to)`: statements dated from `$from` to `$to`, both days included. Only the date counts. Posted statements are included; pass `includePosted: false` for only the ones still to be processed.
- `listByCreationDate(from: …)`: statements imported since a moment, e.g. to pick up new statements since the last run.
- Each `BankStatement` has `bankBook`, `number`, `date`, `openingBalance`, `closingBalance`, `transactionNumber` (null while not posted) and `lines`. A line has a signed `amount` (negative for money paid), the other party (`contraIban`, `contraAccountName`), `description`, `recognition` (how Twinfield recognised it, e.g. `ContraIban` or `AllocationRule`) and `allocations`.
- An allocation has a `type` (`GeneralLedger`, `Customer`, `Supplier`, `Suspense`, …), `dimensions` and `matchings` (the invoice it paid). `signedAmount()` gives it the sign of the line, so the allocations of a line add up to its amount.

Twinfield has no paging: ask for a month or less at a time, as a long period with many statements can time out.

## Code

```php
$twinfield = Example::twinfield();

$days = (int) ($argv[1] ?? 7);
$from = new DateTimeImmutable(sprintf('-%d days', $days));
$to = new DateTimeImmutable('today');

$statements = $twinfield->electronicBankStatements()->list($from, $to);

echo 'Company ', $twinfield->companyCode(), PHP_EOL;
echo count($statements), ' statement(s) dated ', $from->format('Y-m-d'), ' to ', $to->format('Y-m-d'), PHP_EOL;

foreach ($statements as $index => $statement) {
    echo sprintf(
        '[%d/%d] %s %d of %s: %s to %s, %d line(s), %s',
        $index + 1,
        count($statements),
        $statement->bankBook,
        $statement->number,
        $statement->date->format('Y-m-d'),
        $statement->openingBalance,
        $statement->closingBalance,
        count($statement->lines),
        $statement->isPosted() ? 'posted as ' . $statement->transactionNumber : 'not posted',
    ), PHP_EOL;

    foreach ($statement->lines as $line) {
        $allocations = array_map(
            static fn (BankStatementAllocation $allocation): string => sprintf(
                '%s %s %s',
                $allocation->type->value,
                implode('/', $allocation->dimensions),
                $allocation->signedAmount(),
            ),
            $line->allocations,
        );

        echo sprintf(
            '  %3d %10s  %-30.30s  %s%s',
            $line->lineId,
            $line->amount,
            $line->contraAccountName ?? $line->description ?? '',
            implode(', ', $allocations),
            $line->recognition === null ? '' : ' (' . $line->recognition->value . ')',
        ), PHP_EOL;
    }
}
```

## Run

```sh
php examples/electronic-bank-statements/list-electronic-bank-statements/example.php      # last 7 days
php examples/electronic-bank-statements/list-electronic-bank-statements/example.php 31   # last month
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

Output in the format of a real company, with made-up values:

```text
Company 1000
2 statement(s) dated 2026-11-02 to 2026-11-09
[1/2] BNK 1686 of 2026-11-07: 6191.73 to 7366.31, 4 line(s), posted as 202600314
    1    1262.87  Payment provider                GeneralLedger 1010 1262.87
    2     -85.95  Example Supplies B.V.           Supplier 1600/2001 -85.95 (ContraIban)
    3     -22.34  Telecom Provider                Supplier 1600/2002 -22.34 (AllocationRule)
    4      20.00  Example Customer                Customer 1300/1001 20.00
[2/2] BNK 1687 of 2026-11-08: 7366.31 to 8556.08, 1 line(s), posted as 202600313
    1    1189.77  Payment provider                GeneralLedger 1010 1189.77
```
