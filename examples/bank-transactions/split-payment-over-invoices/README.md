# Split payment over invoices

A customer pays several invoices with one bank transfer. This example divides the amount over the open invoices, oldest first: each invoice gets its own `receive()` line with the invoice number, so Twinfield can match the payment with each invoice. What is left after the last invoice is booked as prepayment on the customer.

By default it is a dry run; add `--save` to create the transaction as provisional. Pass another amount as first argument, e.g. `2000.00`.

## Code

```php
$twinfield = Example::twinfield();

// One payment from customer 1001, for several open invoices (oldest first).
$customer = '1001';
$received = Amount::fromString($argv[1] ?? '1250.00');
$openInvoices = ['2026-0039' => '400.00', '2026-0040' => '605.00', '2026-0041' => '500.00'];

$input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'));
$left = $received;

foreach ($openInvoices as $invoice => $open) {
    if (!$left->isPositive()) {
        break;
    }

    // Pay the invoice in full, or what is left of the payment.
    $amount = $left->compare($open) >= 0 ? Amount::of($open) : $left;
    $left = $left->minus($amount);

    $input = $input->receive('1300', $amount, customer: $customer, invoice: $invoice, description: $invoice);

    echo sprintf('%s: %8s of %8s%s', $invoice, $amount, $open, $amount->equals($open) ? '' : ' (partly)'), PHP_EOL;
}

if ($left->isPositive()) {
    // More than all open invoices: book the rest on the customer without invoice.
    $input = $input->receive('1300', $left, customer: $customer, description: 'Prepayment');

    echo 'Prepayment: ', $left, PHP_EOL;
}

echo 'Received ', $received, ' in ', count($input->lines()), ' lines', PHP_EOL;

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
php examples/bank-transactions/split-payment-over-invoices/example.php
```

```text
2026-0039:   400.00 of   400.00
2026-0040:   605.00 of   605.00
2026-0041:   245.00 of   500.00 (partly)
Received 1250.00 in 3 lines

Dry run; add --save to create it as provisional transaction:

<transaction destiny="temporary">
  <header>
    <office>1002</office>
    <code>BNK</code>
    <date>20261103</date>
  </header>
  <lines>
    <line type="total" id="1">
      <dim1>1010</dim1>
      <debitcredit>debit</debitcredit>
      <value>1250.00</value>
    </line>
    <line type="detail" id="2">
      <dim1>1300</dim1>
      <dim2>1001</dim2>
      <debitcredit>credit</debitcredit>
      <value>400.00</value>
      <invoicenumber>2026-0039</invoicenumber>
      <description>2026-0039</description>
    </line>
    <line type="detail" id="3">
      <dim1>1300</dim1>
      <dim2>1001</dim2>
      <debitcredit>credit</debitcredit>
      <value>605.00</value>
      <invoicenumber>2026-0040</invoicenumber>
      <description>2026-0040</description>
    </line>
    <line type="detail" id="4">
      <dim1>1300</dim1>
      <dim2>1001</dim2>
      <debitcredit>credit</debitcredit>
      <value>245.00</value>
…
```
