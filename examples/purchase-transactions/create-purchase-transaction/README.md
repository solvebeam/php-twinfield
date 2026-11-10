# Create purchase transaction

Books an invoice you received from a supplier as purchase transaction in day book `INK`, then deletes it again. Without `--save` it only shows the request. With `--save` it changes data in Twinfield.

<!-- twinfield-documentation:start -->
Twinfield documentation: [Purchase transactions](https://developers.twinfield.com/documentation/api/transaction-data/purchase-transactions/).
<!-- twinfield-documentation:end -->

`PurchaseTransactionInput::invoice($dayBook, $supplier, $invoiceNumber, $date)` starts the input, with the invoice number **of the supplier**. A purchase transaction has:

- a **total line** with the supplier (`dim2`) and the amount **including** VAT. The input calculates it from the other lines: `credit` for an invoice, `debit` for a credit note. Without `payablesAccount()`, Twinfield uses the accounts payable account of the supplier;
- a **detail line** per `expense()`: the cost account (`dim1`) with the amount **without** VAT and a VAT code. For an invoice it is `debit`.

With a `VatCode` object, `expense()` calculates the VAT with its percentage on the invoice date (`$vatCode->vatOn()`), so the total line matches what Twinfield calculates. With a VAT code as string, give the VAT amount too (`vatValue: '21.00'`). Amounts are `Amount` objects or numeric strings, never floats.

Two suppliers can use the same invoice number. Twinfield then warns; `ignoreDuplicateInvoiceWarning()` turns that off. `excludeFromPaymentRuns()` leaves the invoice out of the payment runs of Twinfield.

New transactions are provisional, so they can still be changed or deleted. Call `final()` on the input to post it at once. `create()` returns the `PurchaseTransaction` as Twinfield saved it, with the transaction number, the VAT lines Twinfield added and the open amount.

The example uses the first supplier, the first profit and loss account and VAT code `IH` (or the first purchase VAT code). In your own application you use your cost account, e.g. `7000`.

## Code

```php
$twinfield = Example::twinfield();
$purchases = $twinfield->purchaseTransactions();

// In your own application: your purchase day book, supplier, cost account and VAT code.
$supplier = Example::supplierCode($twinfield);
$costs = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::purchaseVatCode($twinfield));
$date = new DateTimeImmutable('today');

// Book a received invoice with the invoice number of the supplier. With a VatCode, the VAT is
// calculated with its percentage on the invoice date, so the total line matches Twinfield.
$input = PurchaseTransactionInput::invoice('INK', $supplier, 'EXAMPLE-' . $date->format('Ymd'), $date)
    ->dueDate($date->modify('+14 days'))
    ->expense($costs, '100.00', vatCode: $vatCode, description: 'Example purchase');

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to create it as provisional transaction and delete it again:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml((string) $twinfield->companyCode())), PHP_EOL;

    exit(0);
}

try {
    $transaction = $purchases->create($input);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Created:  ', $transaction->dayBook(), ' ', $transaction->number(), ' (', $transaction->header->status->name, ')', PHP_EOL;
echo 'Invoice:  ', $transaction->invoiceNumber, ', due ', $transaction->dueDate?->format('Y-m-d') ?? '-', PHP_EOL;
echo 'Supplier: ', $transaction->supplier()->code, ' ', $transaction->supplier()->name, PHP_EOL;
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
$purchases->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted:  ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
```

## Run

```sh
php examples/purchase-transactions/create-purchase-transaction/example.php
```

```text
Dry run; add --save to create it as provisional transaction and delete it again:

<transaction destiny="temporary">
  <header>
    <office>1002</office>
    <code>INK</code>
    <date>20261109</date>
    <duedate>20261123</duedate>
    <invoicenumber>EXAMPLE-20261109</invoicenumber>
  </header>
  <lines>
    <line type="total" id="1">
      <dim2>2000</dim2>
      <debitcredit>credit</debitcredit>
      <value>121.00</value>
    </line>
    <line type="detail" id="2">
      <dim1>40000</dim1>
      <debitcredit>debit</debitcredit>
      <value>100.00</value>
      <description>Example purchase</description>
      <vatcode>IH</vatcode>
      <vatvalue>21.00</vatvalue>
    </line>
  </lines>
</transaction>
```

Add `--save` to send it. This creates a provisional transaction and deletes it again:

```sh
php examples/purchase-transactions/create-purchase-transaction/example.php --save
```

<!-- xml:start -->
## XML

<!-- Generated by `composer docs:xml` from tests/Support/Docs/ExampleXmlDefinitions.php, do not edit. -->

What the SDK sends to Twinfield and what Twinfield sends back, for a call like the one in this example. The responses come from the tests, with example data.

### Create

Twinfield documentation: [Purchase transactions: Create](https://developers.twinfield.com/documentation/api/transaction-data/purchase-transactions/#createupdate).

```php
$twinfield->purchaseTransactions()->create(
    PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-0044', new DateTimeImmutable('2026-11-09'))
        ->dueDate(new DateTimeImmutable('2026-12-09'))
        ->paymentReference('F-2026-0044')
        ->expense('7000', '100.00', vatCode: 'IH', vatValue: '21.00', description: 'Consultancy'),
);
```

The SDK sends this XML as `xmlRequest` of `ProcessXmlString` to `processxml.asmx`:

```xml
<transaction destiny="temporary">
  <header>
    <office>1000</office>
    <code>INK</code>
    <date>20261109</date>
    <duedate>20261209</duedate>
    <invoicenumber>F-2026-0044</invoicenumber>
    <paymentreference>F-2026-0044</paymentreference>
  </header>
  <lines>
    <line type="total" id="1">
      <dim2>2000</dim2>
      <debitcredit>credit</debitcredit>
      <value>121.00</value>
    </line>
    <line type="detail" id="2">
      <dim1>7000</dim1>
      <debitcredit>debit</debitcredit>
      <value>100.00</value>
      <description>Consultancy</description>
      <vatcode>IH</vatcode>
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
    <code name="Inkoopfactuur" shortname="Inkoop">INK</code>
    <number>202600014</number>
    <period>2026/11</period>
    <currency name="Euro" shortname="Euro">EUR</currency>
    <regime>Generic</regime>
    <date>20261109</date>
    <origin>import</origin>
    <originreference/>
    <user name="Example user" shortname="User">EXAMPLE</user>
    <duedate>20261209</duedate>
    <invoicenumber>F-2026-0044</invoicenumber>
    <paymentreference>F-2026-0044</paymentreference>
  </header>
  <lines>
    <line type="total" id="1">
      <dim1 name="Crediteuren" shortname="" dimensiontype="BAS">1600</dim1>
      <dim2 name="Example supplier" shortname="" dimensiontype="CRD">2000</dim2>
      <debitcredit>credit</debitcredit>
      <basevalue>121.00</basevalue>
      <rate>1</rate>
      <value>121.00</value>
      <vatbasetotal>21.00</vatbasetotal>
      <vattotal>21.00</vattotal>
      <matchstatus>available</matchstatus>
      <matchlevel>2</matchlevel>
      <relation>2</relation>
      <basevalueopen>-121.00</basevalueopen>
    </line>
    <line type="detail" id="2">
      <dim1 name="Inkoop hoog" shortname="" dimensiontype="PNL">7000</dim1>
      <debitcredit>debit</debitcredit>
      <basevalue>100.00</basevalue>
      <rate>1</rate>
      <value>100.00</value>
      <description>Consultancy</description>
      <vatcode name="BTW Hoog" shortname="I Hoog" type="purchase">IH</vatcode>
      <vatbasevalue>21.00</vatbasevalue>
      <vatvalue>21.00</vatvalue>
      <matchstatus>notmatchable</matchstatus>
    </line>
    <line type="vat" id="3">
      <dim1 name="Te vorderen btw hoog" shortname="" dimensiontype="BAS">1520</dim1>
      <debitcredit>debit</debitcredit>
      <basevalue>21.00</basevalue>
      <rate>1</rate>
      <value>21.00</value>
      <vatbaseturnover>100.00</vatbaseturnover>
      <vatturnover>100.00</vatturnover>
      <vatcode name="BTW Hoog" shortname="I Hoog" type="purchase">IH</vatcode>
      <baseline>1</baseline>
      <matchstatus>available</matchstatus>
      <matchlevel>1</matchlevel>
      <relation>0</relation>
      <basevalueopen>21.00</basevalueopen>
    </line>
  </lines>
</transaction>
```
<!-- xml:end -->
