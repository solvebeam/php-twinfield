# Import bank statement

A bank statement from a CSV file (see [`statement.csv`](statement.csv)) becomes one bank transaction, with a line per bank line. It logs what it does with each line, and checks that the closing balance matches the statement.

The rules are the ones most imports start with:

- a known customer IBAN: `receive()` on accounts receivable, with the customer and, when the description has one invoice number, that invoice;
- a known supplier IBAN: `pay()` on accounts payable, with the supplier;
- no IBAN and money out: bank costs;
- anything else: the suspense account, to sort out in Twinfield.

Change the accounts and relations at the top to those of your administration. By default it is a dry run: it shows the request it would send. Add `--save` to create the transaction as provisional, so you can check it in Twinfield before posting it.

## Code

```php
$twinfield = Example::twinfield();

// Your chart of accounts and relations: change these to match your administration.
$bankBook = 'BNK';
$bankAccount = '1010';
$accounts = ['receivable' => '1300', 'payable' => '1600', 'bankCosts' => '4500', 'suspense' => '2300'];
$customers = ['NL91ABNA0417164300' => '1000', 'DE89370400440532013000' => '1001'];
$suppliers = ['NL20INGB0001234567' => '2000'];

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

$date = new DateTimeImmutable($lines[0][0] ?? 'today');

$input = BankTransactionInput::create($bankBook, $bankAccount, $date)
    ->statement((int) $statementNumber, startValue: Amount::fromString($opening))
    ->description('Statement ' . $statementNumber);

echo 'Statement ', $statementNumber, ': ', count($lines), ' lines', PHP_EOL;

foreach ($lines as $index => $line) {
    [$date, $amount, $name, $iban, $description] = $line + ['', '', '', '', ''];
    $amount = Amount::fromString($amount);
    $invoices = preg_match_all('~\b20\d{2}-\d{4}\b~', $description, $matches) > 0 ? $matches[0] : [];
    // One invoice: book it on that invoice. More invoices: leave the invoice empty and match later.
    $invoice = count($invoices) === 1 ? $invoices[0] : null;
    $text = mb_substr($name . ' ' . $description, 0, 40);

    [$action, $input] = match (true) {
        isset($customers[$iban]) && !$amount->isNegative() => [
            sprintf('receive %s customer %s', $accounts['receivable'], $customers[$iban])
                . ($invoice === null ? '' : ", invoice {$invoice}"),
            $input->receive(
                $accounts['receivable'],
                $amount,
                customer: $customers[$iban],
                invoice: $invoice,
                description: $text,
            ),
        ],
        isset($suppliers[$iban]) && $amount->isNegative() => [
            sprintf('pay %s supplier %s', $accounts['payable'], $suppliers[$iban]),
            $input->pay($accounts['payable'], $amount->absolute(), supplier: $suppliers[$iban], description: $text),
        ],
        $iban === '' && $amount->isNegative() => [
            sprintf('pay %s bank costs', $accounts['bankCosts']),
            $input->pay($accounts['bankCosts'], $amount->absolute(), description: $text),
        ],
        default => [
            ($amount->isNegative() ? 'unknown: pay from' : 'unknown: receive on')
                . ' suspense account ' . $accounts['suspense'],
            $amount->isNegative()
                ? $input->pay($accounts['suspense'], $amount->absolute(), description: $text)
                : $input->receive($accounts['suspense'], $amount, description: $text),
        ],
    };

    echo sprintf(
        '[%d/%d] %s %10s  %-18s → %s',
        $index + 1,
        count($lines),
        $date,
        ($amount->isNegative() ? '' : '+') . $amount,
        $name,
        $action,
    ), PHP_EOL;
}

if (!$input->closeValue()?->equals(Amount::fromString($closing))) {
    echo 'The lines do not add up: closing balance ', $input->closeValue(), ', statement says ', $closing, '.', PHP_EOL;

    exit(1);
}

echo 'Closing balance ', $input->closeValue(), ' matches the statement.', PHP_EOL;

if (!in_array('--save', $argv ?? [], true)) {
    echo PHP_EOL, 'Dry run; add --save to create it as provisional transaction:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml($twinfield->companyCode() ?? '')), PHP_EOL;

    exit(0);
}

$transaction = $twinfield->bankTransactions()->create($input);

echo 'Created provisional transaction ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
```

## Run

```sh
php examples/bank-transactions/import-bank-statement/example.php
```

```text
Statement 245: 5 lines
[1/5] 2026-11-03    +121.00  Example customer   → receive 1300 customer 1000, invoice 2026-0042
[2/5] 2026-11-03    -605.00  Example supplier   → pay 1600 supplier 2000
[3/5] 2026-11-03   +1250.00  Another customer   → receive 1300 customer 1001
[4/5] 2026-11-03     -12.10  Example bank       → pay 4500 bank costs
[5/5] 2026-11-03     -50.00  Unknown            → unknown: pay from suspense account 2300
Closing balance 1703.90 matches the statement.

Dry run; add --save to create it as provisional transaction:

<transaction destiny="temporary">
  <header>
    <office>1002</office>
    <code>BNK</code>
    <date>20261103</date>
    <statementnumber>245</statementnumber>
    <startvalue>1000.00</startvalue>
    <closevalue>1703.90</closevalue>
  </header>
  <lines>
    <line type="total" id="1">
      <dim1>1010</dim1>
      <debitcredit>debit</debitcredit>
      <value>703.90</value>
      <description>Statement 245</description>
    </line>
    <line type="detail" id="2">
      <dim1>1300</dim1>
      <dim2>1000</dim2>
      <debitcredit>credit</debitcredit>
      <value>121.00</value>
      <invoicenumber>2026-0042</invoicenumber>
      <description>Example customer Invoice 2026-0042</description>
    </line>
    <line type="detail" id="3">
      <dim1>1600</dim1>
      <dim2>2000</dim2>
      <debitcredit>debit</debitcredit>
      <value>605.00</value>
      <description>Example supplier INV-778 office chairs</description>
…
```
