# Create sales transaction

Books an invoice from your own software as sales transaction in day book `VRK`, then deletes it again. Without `--save` it only shows the request. With `--save` it changes data in Twinfield.

<!-- twinfield-documentation:start -->
Twinfield documentation: [Sales transactions](https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/).
<!-- twinfield-documentation:end -->

Use a sales transaction when the invoice is already made in your own software: Twinfield only gets the booking, not an invoice. See [Sales invoice or sales transaction?](https://php-twinfield.solvebeam.com/knowledge-base/sales-invoice-or-sales-transaction/) for the difference with a sales invoice.

`SalesTransactionInput::invoice($dayBook, $customer, $invoiceNumber, $date)` starts the input. A sales transaction has:

- a **total line** with the customer (`dim2`) and the amount **including** VAT. The input calculates it from the other lines: `debit` for an invoice, `credit` for a credit note. Without `receivablesAccount()`, Twinfield uses the accounts receivable account of the customer;
- a **detail line** per `revenue()`: the revenue account (`dim1`) with the amount **without** VAT and a VAT code. For an invoice it is `credit`.

With a `VatCode` object, `revenue()` calculates the VAT with its percentage on the invoice date (`$vatCode->vatOn()`), so the total line matches what Twinfield calculates. With a VAT code as string, give the VAT amount too (`vatValue: '21.00'`). Amounts are `Amount` objects or numeric strings, never floats.

New transactions are provisional, so they can still be changed or deleted. Call `final()` on the input to post it at once. `create()` returns the `SalesTransaction` as Twinfield saved it, with the transaction number, the VAT lines Twinfield added and the open amount.

The example uses the first customer, the first profit and loss account and VAT code `VH` (or the first sales VAT code). In your own application you use your revenue account, e.g. `8000`.

## Code

```php
$twinfield = Example::twinfield();
$sales = $twinfield->salesTransactions();

// In your own application: your sales day book, customer, revenue account and VAT code.
$customer = Example::customerCodes($twinfield, 1)[0];
$revenue = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::salesVatCode($twinfield));
$date = new DateTimeImmutable('today');

// The invoice was made in your own software: book its amounts. With a VatCode, the VAT is
// calculated with its percentage on the invoice date, so the total line matches Twinfield.
$input = SalesTransactionInput::invoice('VRK', $customer, 'EXAMPLE-' . $date->format('Ymd'), $date)
    ->dueDate($date->modify('+30 days'))
    ->revenue($revenue, '100.00', vatCode: $vatCode, description: 'Example sale');

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

echo 'Created:  ', $transaction->dayBook(), ' ', $transaction->number(), ' (', $transaction->header->status->name, ')', PHP_EOL;
echo 'Invoice:  ', $transaction->invoiceNumber, ', due ', $transaction->dueDate->format('Y-m-d'), PHP_EOL;
echo 'Customer: ', $transaction->customer()->code, ' ', $transaction->customer()->name, PHP_EOL;
echo 'Total:    ', $transaction->value(), ' including ', $transaction->total->vatTotal, ' VAT, open ',
    $transaction->total->openBaseValue, PHP_EOL;

foreach ($transaction->details as $line) {
    echo '  detail ', $line->account->code, ' ', $line->debitCredit->value, ' ', $line->amounts->value,
        ' ', $line->vatCode, ' ', $line->vatValue, PHP_EOL;
}

foreach ($transaction->vat as $line) {
    echo '  vat    ', $line->account->code, ' ', $line->debitCredit->value, ' ', $line->amounts->value, PHP_EOL;
}

// Clean up: delete the provisional example transaction again.
$sales->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted:  ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
```

## Run

```sh
php examples/sales-transactions/create-sales-transaction/example.php
```

```text
Dry run; add --save to create it as provisional transaction and delete it again:

<transaction destiny="temporary">
  <header>
    <office>1002</office>
    <code>VRK</code>
    <date>20261109</date>
    <duedate>20261209</duedate>
    <invoicenumber>EXAMPLE-20261109</invoicenumber>
  </header>
  <lines>
    <line type="total" id="1">
      <dim2>1000</dim2>
      <debitcredit>debit</debitcredit>
      <value>121.00</value>
    </line>
    <line type="detail" id="2">
      <dim1>40000</dim1>
      <debitcredit>credit</debitcredit>
      <value>100.00</value>
      <description>Example sale</description>
      <vatcode>VH</vatcode>
      <vatvalue>21.00</vatvalue>
    </line>
  </lines>
</transaction>
```

Add `--save` to send it. This creates a provisional transaction and deletes it again:

```sh
php examples/sales-transactions/create-sales-transaction/example.php --save
```

<!-- xml:start -->
## XML

<!-- Generated by `composer docs:xml` from tests/Support/Docs/ExampleXmlDefinitions.php, do not edit. -->

What the SDK sends to Twinfield and what Twinfield sends back, for a call like the one in this example. The responses come from the tests, with example data.

### Create

Twinfield documentation: [Sales transactions: Create](https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/#createupdate).

```php
$twinfield->salesTransactions()->create(
    SalesTransactionInput::invoice('VRK', '1000', '2026-0044', new DateTimeImmutable('2026-11-09'))
        ->dueDate(new DateTimeImmutable('2026-12-09'))
        ->paymentReference('2026-0044')
        ->revenue('8000', '100.00', vatCode: 'VH', vatValue: '21.00', description: 'Consultancy'),
);
```

The SDK sends this XML as `xmlRequest` of `ProcessXmlString` to `processxml.asmx`:

```xml
<transaction destiny="temporary">
  <header>
    <office>1000</office>
    <code>VRK</code>
    <date>20261109</date>
    <duedate>20261209</duedate>
    <invoicenumber>2026-0044</invoicenumber>
    <paymentreference>2026-0044</paymentreference>
  </header>
  <lines>
    <line type="total" id="1">
      <dim2>1000</dim2>
      <debitcredit>debit</debitcredit>
      <value>121.00</value>
    </line>
    <line type="detail" id="2">
      <dim1>8000</dim1>
      <debitcredit>credit</debitcredit>
      <value>100.00</value>
      <description>Consultancy</description>
      <vatcode>VH</vatcode>
      <vatvalue>21.00</vatvalue>
    </line>
  </lines>
</transaction>
```

Twinfield answers with this XML in `ProcessXmlStringResult`:

```xml
<transaction destiny="temporary" result="1" location="temporary">
  <header>
    <office name="Example Holding B.V." shortname="">1000</office>
    <code name="Verkoopfactuur" shortname="Verkoop">VRK</code>
    <number>202600014</number>
    <period>2026/11</period>
    <currency name="Euro" shortname="Euro">EUR</currency>
    <regime>Generic</regime>
    <date>20261109</date>
    <origin>import</origin>
    <originreference/>
    <user name="Example user" shortname="User">EXAMPLE</user>
    <duedate>20261209</duedate>
    <invoicenumber>2026-0044</invoicenumber>
    <paymentreference>2026-0044</paymentreference>
  </header>
  <lines>
    <line type="total" id="1">
      <dim1 name="Debiteuren" shortname="" dimensiontype="BAS">1300</dim1>
      <dim2 name="Example customer" shortname="" dimensiontype="DEB">1000</dim2>
      <debitcredit>debit</debitcredit>
      <basevalue>121.00</basevalue>
      <rate>1</rate>
      <value>121.00</value>
      <vatbasetotal>21.00</vatbasetotal>
      <vattotal>21.00</vattotal>
      <matchstatus>available</matchstatus>
      <matchlevel>2</matchlevel>
      <relation>2</relation>
      <basevalueopen>121.00</basevalueopen>
    </line>
    <line type="detail" id="2">
      <dim1 name="Omzet hoog" shortname="" dimensiontype="PNL">8000</dim1>
      <debitcredit>credit</debitcredit>
      <basevalue>100.00</basevalue>
      <rate>1</rate>
      <value>100.00</value>
      <description>Consultancy</description>
      <vatcode name="BTW Hoog" shortname="V Hoog" type="sales">VH</vatcode>
      <vatbasevalue>21.00</vatbasevalue>
      <vatvalue>21.00</vatvalue>
      <matchstatus>notmatchable</matchstatus>
    </line>
    <line type="vat" id="3">
      <dim1 name="Te betalen btw hoog" shortname="" dimensiontype="BAS">1530</dim1>
      <debitcredit>credit</debitcredit>
      <basevalue>21.00</basevalue>
      <rate>1</rate>
      <value>21.00</value>
      <vatbaseturnover>100.00</vatbaseturnover>
      <vatturnover>100.00</vatturnover>
      <vatcode name="BTW Hoog" shortname="V Hoog" type="sales">VH</vatcode>
      <baseline>1</baseline>
      <matchstatus>available</matchstatus>
      <matchlevel>1</matchlevel>
      <relation>0</relation>
      <basevalueopen>-21.00</basevalueopen>
    </line>
  </lines>
</transaction>
```
<!-- xml:end -->
