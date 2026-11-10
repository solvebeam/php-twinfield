# Create sales invoice

Lets Twinfield make a sales invoice in the Classic invoicing module. Without options it only shows the request. With `--calculate` Twinfield checks and calculates it without saving anything. With `--save` it changes data in Twinfield: it creates a concept invoice that stays there, so delete it in Twinfield afterwards (the API can't delete sales invoices).

<!-- twinfield-documentation:start -->
Twinfield documentation: [Sales invoices](https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/).
<!-- twinfield-documentation:end -->

Use a sales invoice when Twinfield makes and sends the invoice. Twinfield gives the invoice number, calculates the amounts and VAT from the articles, and books the sales transaction when the invoice is final. See [Sales invoice or sales transaction?](https://php.twinfield.solvebeam.com/knowledge-base/sales-invoice-or-sales-transaction/) for the difference with a sales transaction.

`SalesInvoiceInput::create($invoiceType, $customer)` starts a concept invoice:

- `invoiceDate()`, `dueDate()`, `period()`, `bank()`, `currency()`, `paymentMethod()`, `headerText()` and `footerText()` are optional; Twinfield takes the defaults of the invoice type and the customer.
- `article($code, $quantity)` adds an article: Twinfield takes its price, VAT code and account.
- `oneOff($description, $price, vatCode: …)` adds the one-off item `0`, which is in every company: your own description and price per unit without VAT. Twinfield books it on the account of the invoice type.
- `final()` makes the invoice final at once.

`calculate()` returns a `SalesInvoiceCalculation` with the lines, totals and VAT; `create()` returns the saved `SalesInvoice` with its number.

The example uses invoice type `FACTUUR`, the first customer and VAT code `VH` (or the first sales VAT code). List the invoice types of your company with [List invoice types](../list-invoice-types/).

## Code

```php
$twinfield = Example::twinfield();
$invoices = $twinfield->salesInvoices();

// In your own application: your invoice type, customer and VAT code.
$invoiceType = 'FACTUUR';
$customer = Example::customerCodes($twinfield, 1)[0];
$vatCode = Example::salesVatCode($twinfield);
$date = new DateTimeImmutable('today');

// Twinfield makes the invoice: it gives the number and calculates the amounts and VAT. The
// one-off item `0` takes your own description and price; other articles their own.
$input = SalesInvoiceInput::create($invoiceType, $customer)
    ->invoiceDate($date)
    ->dueDate($date->modify('+30 days'))
    ->paymentMethod(PaymentMethod::Bank)
    ->oneOff('Example sale', '100.00', vatCode: $vatCode);

$mode = $argv[1] ?? '';

if ($mode !== '--calculate' && $mode !== '--save') {
    echo 'Dry run; add --calculate to let Twinfield calculate it without saving, or --save to create it',
        ' as concept invoice (it is not deleted again):', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml((string) $twinfield->companyCode())), PHP_EOL;

    exit(0);
}

try {
    if ($mode === '--calculate') {
        $calculation = $invoices->calculate($input);

        echo 'Calculated: ', $calculation->totals->valueExcl, ' + ', $calculation->totals->vat(), ' VAT = ',
            $calculation->totals->valueInc, ' (not saved)', PHP_EOL;

        exit(0);
    }

    $invoice = $invoices->create($input);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Created:  ', $invoice->invoiceType, ' ', $invoice->invoiceNumber, ' (', $invoice->status->value, ')', PHP_EOL;
echo 'Total:    ', $invoice->totals->valueExcl, ' + ', $invoice->totals->vat(), ' VAT = ',
    $invoice->totals->valueInc, ' ', $invoice->currency, PHP_EOL;

foreach ($invoice->lines as $line) {
    echo '  ', $line->quantity, ' x ', $line->description, ' ', $line->valueExcl, ' ', $line->vatCode,
        ' on ', $line->dim1->code, PHP_EOL;
}
```

## Run

```sh
php examples/sales-invoices/create-sales-invoice/example.php
```

```text
Dry run; add --calculate to let Twinfield calculate it without saving, or --save to create it as concept invoice (it is not deleted again):

<salesinvoice>
  <header>
    <office>1002</office>
    <invoicetype>FACTUUR</invoicetype>
    <invoicedate>20261109</invoicedate>
    <duedate>20261209</duedate>
    <customer>1000</customer>
    <status>concept</status>
    <paymentmethod>bank</paymentmethod>
  </header>
  <lines>
    <line id="1">
      <article>0</article>
      <quantity>1</quantity>
      <unitspriceexcl>100.00</unitspriceexcl>
      <vatcode>VH</vatcode>
      <description>Example sale</description>
    </line>
  </lines>
</salesinvoice>
```

Add `--calculate` to see the amounts Twinfield calculates, without saving:

```sh
php examples/sales-invoices/create-sales-invoice/example.php --calculate
```

Add `--save` to create the concept invoice. It stays in Twinfield:

```sh
php examples/sales-invoices/create-sales-invoice/example.php --save
```

<!-- xml:start -->
## XML

<!-- Generated by `composer docs:xml` from tests/Support/Docs/ExampleXmlDefinitions.php, do not edit. -->

What the SDK sends to Twinfield and what Twinfield sends back, for a call like the one in this example. The responses come from the tests, with example data.

### Calculate

Twinfield documentation: [Sales invoices: Calculate](https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/#createupdate).

```php
$twinfield->salesInvoices()->calculate(
    SalesInvoiceInput::create('FACTUUR', '1000')->article('510', '2', '501.01'),
);
```

The SDK sends this XML as `xmlRequest` of `ProcessXmlString` to `processxml.asmx`:

```xml
<salesinvoice calculateonly="true">
  <header>
    <office>1000</office>
    <invoicetype>FACTUUR</invoicetype>
    <customer>1000</customer>
    <status>concept</status>
  </header>
  <lines>
    <line id="1">
      <article>510</article>
      <subarticle>501.01</subarticle>
      <quantity>2</quantity>
    </line>
  </lines>
</salesinvoice>
```

Twinfield answers with this XML in `ProcessXmlStringResult`:

```xml
<salesinvoice calculateonly="true" result="1">
  <header>
    <office>1000</office>
    <invoicetype>FACTUUR</invoicetype>
    <invoicedate>20261109</invoicedate>
    <duedate>20261209</duedate>
    <bank>BNK</bank>
    <customer>1000</customer>
    <period>2026/11</period>
    <currency>EUR</currency>
    <status>concept</status>
    <paymentmethod>bank</paymentmethod>
    <invoiceaddressnumber>1</invoiceaddressnumber>
    <deliveraddressnumber>1</deliveraddressnumber>
    <headertext/>
    <footertext/>
  </header>
  <lines>
    <line id="1">
      <article>510</article>
      <subarticle>501.01</subarticle>
      <quantity>2.00</quantity>
      <units>1</units>
      <unitspriceexcl>41.97</unitspriceexcl>
      <vatcode name="VH" shortname="VH">VH</vatcode>
      <allowdiscountorpremium>true</allowdiscountorpremium>
      <description>Trousers</description>
      <performancedate/>
      <performancetype/>
      <freetext1/>
      <freetext2/>
      <freetext3/>
      <dim1 name="Omzet" shortname="Omzet">8020</dim1>
      <valueexcl>83.94</valueexcl>
      <vatvalue>17.63</vatvalue>
      <valueinc>101.57</valueinc>
    </line>
  </lines>
  <totals>
    <valueexcl>83.94</valueexcl>
    <valueinc>101.57</valueinc>
  </totals>
  <vatlines>
    <vatline>
      <vatcode name="VH" shortname="VH" percentage="21.000000000">VH</vatcode>
      <vatvalue>17.63</vatvalue>
      <performancetype/>
      <performancedate/>
    </vatline>
  </vatlines>
</salesinvoice>
```

### Create

Twinfield documentation: [Sales invoices: Create](https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/#createupdate).

```php
$twinfield->salesInvoices()->create(
    SalesInvoiceInput::create('FACTUUR', '1000')
        ->paymentMethod(PaymentMethod::Bank)
        ->article('510', quantity: '2', subarticle: '501.01'),
);
```

The SDK sends this XML as `xmlRequest` of `ProcessXmlString` to `processxml.asmx`:

```xml
<salesinvoice>
  <header>
    <office>1000</office>
    <invoicetype>FACTUUR</invoicetype>
    <customer>1000</customer>
    <status>concept</status>
    <paymentmethod>bank</paymentmethod>
  </header>
  <lines>
    <line id="1">
      <article>510</article>
      <subarticle>501.01</subarticle>
      <quantity>2</quantity>
    </line>
  </lines>
</salesinvoice>
```

Twinfield answers with this XML in `ProcessXmlStringResult`:

```xml
<salesinvoice result="1">
  <header>
    <office>1000</office>
    <invoicetype>FACTUUR</invoicetype>
    <invoicedate>20261109</invoicedate>
    <duedate>20261209</duedate>
    <bank>BNK</bank>
    <customer>1000</customer>
    <period>2026/11</period>
    <currency>EUR</currency>
    <status>concept</status>
    <paymentmethod>bank</paymentmethod>
    <invoiceaddressnumber>1</invoiceaddressnumber>
    <deliveraddressnumber>1</deliveraddressnumber>
    <headertext/>
    <footertext/>
    <invoicenumber>13</invoicenumber>
  </header>
  <lines>
    <line id="1">
      <article>510</article>
      <subarticle>501.01</subarticle>
      <quantity>2.00</quantity>
      <units>1</units>
      <unitspriceexcl>41.97</unitspriceexcl>
      <vatcode name="VH" shortname="VH">VH</vatcode>
      <allowdiscountorpremium>true</allowdiscountorpremium>
      <description>Trousers</description>
      <performancedate/>
      <performancetype/>
      <freetext1/>
      <freetext2/>
      <freetext3/>
      <dim1 name="Omzet" shortname="Omzet">8020</dim1>
      <valueexcl>83.94</valueexcl>
      <vatvalue>17.63</vatvalue>
      <valueinc>101.57</valueinc>
    </line>
  </lines>
  <totals>
    <valueexcl>83.94</valueexcl>
    <valueinc>101.57</valueinc>
  </totals>
  <vatlines>
    <vatline>
      <vatcode name="VH" shortname="VH" percentage="21.000000000">VH</vatcode>
      <vatvalue>17.63</vatvalue>
      <performancetype/>
      <performancedate/>
    </vatline>
  </vatlines>
</salesinvoice>
```
<!-- xml:end -->
