# Create sales invoice

Creates a concept sales invoice in Twinfield with ProcessXml. Without `--save` it only shows the request. With `--save` it changes data in Twinfield, and the concept invoice stays there: delete it in Twinfield afterwards.

Use a sales invoice when Twinfield makes and sends the invoice. Twinfield calculates the amounts and VAT from the articles, and books the sales transaction when the invoice is final. See [Sales invoice or sales transaction?](https://php-twinfield.solvebeam.com/knowledge-base/sales-invoice-or-sales-transaction/) for the difference with a sales transaction.

The SDK has no resource for sales invoices yet, so the example builds the `<salesinvoice>` with `XmlBuilder`:

- `invoicetype`: the code of an invoice type of the company, e.g. `FACTUUR`;
- `status`: `concept` saves a provisional invoice that can still be changed; `final` makes it final, and then Twinfield books the sales transaction and returns it in `financials` (`code` and `number`);
- `paymentmethod`: `cash`, `bank`, `cheque`, `cashondelivery` or `da`;
- a line per article: `article`, `quantity`, `units`, and `unitspriceexcl` only when the price of the article may be changed.

Twinfield returns the invoice number in `header/invoicenumber` and the totals in `totals`.

The example uses invoice type `FACTUUR`, article `0`, the first customer and VAT code `VH` (or the first sales VAT code). Change them to an invoice type and article of your company.

## Code

```php
$twinfield = Example::twinfield();

// In your own application: your invoice type, customer, article and VAT code.
$invoiceType = 'FACTUUR';
$customer = Example::customerCodes($twinfield, 1)[0];
$article = '0';
$vatCode = Example::salesVatCode($twinfield);

$xml = XmlBuilder::create('salesinvoice');

$header = $xml->add($xml->root(), 'header');
$xml->add($header, 'office', (string) $twinfield->companyCode());
$xml->add($header, 'invoicetype', $invoiceType);
$xml->add($header, 'customer', $customer);
// concept: provisional, can still be changed. final: no changes anymore, and Twinfield books
// the sales transaction.
$xml->add($header, 'status', 'concept');
$xml->add($header, 'paymentmethod', 'bank');

$lines = $xml->add($xml->root(), 'lines');

// Twinfield calculates the amounts, the VAT and the totals from the article.
$line = $xml->add($lines, 'line', attributes: ['id' => '1']);
$xml->add($line, 'article', $article);
$xml->add($line, 'quantity', '1');
$xml->add($line, 'units', '1');
// Only when the price of the article may be changed.
$xml->add($line, 'unitspriceexcl', '100.00');
$xml->add($line, 'vatcode', $vatCode);
$xml->add($line, 'description', 'Example sale');

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to create it as concept invoice (it is not deleted again):', PHP_EOL, PHP_EOL;
    echo Example::xml($xml), PHP_EOL;

    exit(0);
}

try {
    $result = $twinfield->processXml($xml->toXml());
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

$xpath = $result->xpath();

echo 'Invoice:  ', $xpath->evaluate('string(/salesinvoice/header/invoicenumber)'),
    ' (', $xpath->evaluate('string(/salesinvoice/header/status)'), ')', PHP_EOL;
echo 'Excl.:    ', $xpath->evaluate('string(/salesinvoice/totals/valueexcl)'), PHP_EOL;
echo 'Incl.:    ', $xpath->evaluate('string(/salesinvoice/totals/valueinc)'), PHP_EOL;

// Only for a final invoice: the sales transaction Twinfield booked.
if ($xpath->evaluate('count(/salesinvoice/financials)') > 0) {
    echo 'Booked:   ', $xpath->evaluate('string(/salesinvoice/financials/code)'),
        ' ', $xpath->evaluate('string(/salesinvoice/financials/number)'), PHP_EOL;
}
```

## Run

```sh
php examples/process-xml/create-sales-invoice/example.php
```

```text
Dry run; add --save to create it as concept invoice (it is not deleted again):

<salesinvoice>
  <header>
    <office>1002</office>
    <invoicetype>FACTUUR</invoicetype>
    <customer>1000</customer>
    <status>concept</status>
    <paymentmethod>bank</paymentmethod>
  </header>
  <lines>
    <line id="1">
      <article>0</article>
      <quantity>1</quantity>
      <units>1</units>
      <unitspriceexcl>100.00</unitspriceexcl>
      <vatcode>VH</vatcode>
      <description>Example sale</description>
    </line>
  </lines>
</salesinvoice>
```

Add `--save` to send it. This creates a concept invoice that stays in Twinfield:

```sh
php examples/process-xml/create-sales-invoice/example.php --save
```
