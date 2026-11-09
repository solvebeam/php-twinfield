# Import electronic bank statement

A bank statement from a CSV file (see [`statement.csv`](statement.csv)) becomes one electronic bank statement in Twinfield, with a line per bank line, just like a statement downloaded from the bank. Twinfield then allocates the lines with the allocation rules of the bank book: to a customer, a supplier, a ledger account or the suspense account.

Compare [Import bank statement](../../bank-transactions/import-bank-statement/), which makes a bank transaction and chooses the accounts in the code. Here the code has no rules: only the IBAN of your bank book, at the top.

The input checks that the opening balance plus the lines is the closing balance. By default it is a dry run: it shows the request it would send. Add `--save` to import it. A statement can't be changed afterwards. Twinfield does not import a statement it already has, unless you pass `importDuplicate: true` to `create()`.

## Code

```php
$twinfield = Example::twinfield();

// The IBAN of your bank book: Twinfield finds the bank book by it.
$iban = 'NL44RABO0123456789';

$handle = fopen($argv[1] ?? __DIR__ . '/statement.csv', 'r') ?: exit('Cannot read the statement.' . PHP_EOL);
$rows = [];

while (($row = fgetcsv($handle, escape: '')) !== false) {
    if ($row !== [null]) {
        $rows[] = array_map(strval(...), $row);
    }
}

// Row 1 and 2: the statement; row 3: the column names; then one row per bank line.
[$statementNumber, $opening, $closing] = $rows[1] + ['', '', ''];
$lines = array_slice($rows, 3);

// The input checks that the opening balance plus the lines is the closing balance.
$input = ElectronicBankStatementInput::forIban($iban)
    ->date(new DateTimeImmutable($lines[0][0] ?? 'today'))
    ->number(
        (int) $statementNumber,
        startValue: Amount::fromString($opening),
        closeValue: Amount::fromString($closing),
    );

echo 'Statement ', $statementNumber, ': ', count($lines), ' lines', PHP_EOL;

foreach ($lines as $index => $line) {
    [$date, $amount, $name, $contraIban, $description] = $line + ['', '', '', '', ''];
    $amount = Amount::fromString($amount);
    $contraIban = $contraIban === '' ? null : $contraIban;

    // No rules here: Twinfield allocates each line with the allocation rules of the bank book.
    $input = $amount->isNegative()
        ? $input->pay($amount->absolute(), $name . ' ' . $description, contraIban: $contraIban)
        : $input->receive($amount, $name . ' ' . $description, contraIban: $contraIban);

    echo sprintf(
        '[%d/%d] %s %10s  %-18s %s',
        $index + 1,
        count($lines),
        $date,
        ($amount->isNegative() ? '' : '+') . $amount,
        $name,
        $description,
    ), PHP_EOL;
}

if (!in_array('--save', $argv ?? [], true)) {
    echo PHP_EOL, 'Dry run; add --save to import it (a statement cannot be changed afterwards):', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml($twinfield->companyCode() ?? '')), PHP_EOL;

    exit(0);
}

$statement = $twinfield->electronicBankStatements()->create($input);

echo 'Imported statement ', $statement->number, ' in bank book ', $statement->bankBook, PHP_EOL;

foreach ($statement->lines as $index => $line) {
    $dimensions = array_map(
        static fn (DimensionReference $dimension): string
            => $dimension->code . ($dimension->name === null ? '' : " ({$dimension->name})"),
        $line->dimensions,
    );

    echo sprintf(
        '[%d/%d] %10s %-40s → %s',
        $index + 1,
        count($statement->lines),
        $line->signedValue(),
        $line->description,
        implode(', ', $dimensions),
    ), PHP_EOL;
}
```

## Run

```sh
php examples/electronic-bank-statements/import-electronic-bank-statement/example.php
php examples/electronic-bank-statements/import-electronic-bank-statement/example.php path/to/statement.csv --save
```

```text
Statement 245: 5 lines
[1/5] 2026-11-03    +121.00  Example customer   Invoice 2026-0042
[2/5] 2026-11-03    -605.00  Example supplier   INV-778 office chairs
[3/5] 2026-11-03   +1250.00  Another customer   Invoices 2026-0040 and 2026-0041
[4/5] 2026-11-03     -12.10  Example bank       Monthly fee November
[5/5] 2026-11-03     -50.00  Unknown            Refund?

Dry run; add --save to import it (a statement cannot be changed afterwards):

<statements importduplicate="0">
  <statement target="electronicstatements">
    <iban>NL44RABO0123456789</iban>
    <date>20261103</date>
    <statementnumber>245</statementnumber>
    <office>1002</office>
    <startvalue>1000.00</startvalue>
    <closevalue>1703.90</closevalue>
    <transactions>
      <transaction>
        <contraiban>NL91ABNA0417164300</contraiban>
        <debitcredit>credit</debitcredit>
        <value>121.00</value>
        <description>Example customer Invoice 2026-0042</description>
      </transaction>
      <transaction>
        <contraiban>NL20INGB0001234567</contraiban>
        <debitcredit>debit</debitcredit>
        <value>605.00</value>
        <description>Example supplier INV-778 office chairs</description>
      </transaction>
…
```

With `--save`, it shows how Twinfield allocated each line, e.g. `121.00 Example customer Invoice 2026-0042 → 1300 (Debtors control), 1000 (Example customer)`.

See [Transactions: Electronic bank statements](https://php-twinfield.solvebeam.com/guides/transactions/#electronic-bank-statements) and the [Twinfield documentation](https://developers.twinfield.com/documentation/api/transaction-data/electronic-bank-statements/).
