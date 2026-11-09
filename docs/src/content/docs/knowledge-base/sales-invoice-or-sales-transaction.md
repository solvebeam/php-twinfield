---
title: Sales invoice or sales transaction?
description: "Twinfield knowledge base: when to create a sales transaction and when a sales invoice through the web services, and how to do both with this SDK."
---

When your software sends sales to Twinfield, there are two options: book a **sales transaction** for an invoice you made yourself, or let Twinfield make the **sales invoice**. This article from the Twinfield community explains the difference.

## The article

**Source:** [Hoe maak ik via webservices een verkoopfactuur of een verkoopboeking aan?](https://wktaaeu.my.site.com/nlcommunity/s/article/Hoe-maak-ik-via-webservices-een-verkoopfactuur-of-een-verkoopboeking-aan?language=nl_NL), Twinfield community (Wolters Kluwer). Created 1 December 2021, updated 3 November 2026, quoted on 9 November 2026.

> **Hoe maak ik via webservices een verkoopfactuur of een verkoopboeking aan?**
>
> In dit artikel wordt uitgelegd wat de opties voor verkooptransacties zijn.
>
> Indien in het aanleverende pakket al een verkoopfactuur wordt aangemaakt, dan volstaat het om in Twinfield een verkoopboeking aan te maken.
>
> Bij verkoopboekingen worden enkel de onderliggende transacties ingelezen. Er zal geen factuur in Twinfield worden gemaakt. De verkoopboeking wordt na het importeren gelijk vastgelegd in het grootboek.
>
> Bij verkoopfacturen kan in Twinfield een factuur worden gemaakt en verstuurd. Nadat de factuur is gejournaliseerd wordt de boeking vastgelegd in het grootboek.
>
> Het is op dit moment niet mogelijk om een koppeling maken tussen de verkoopmodule, de blauwe tegels, en een externe partij. Alle facturen moeten daadwerkelijk ook in Twinfield worden aangemaakt.
>
> In de Classic facturatiemodule is het wel mogelijk om een koppeling te realiseren via de API.
>
> Op basis van een verkooptransactie, dus na het journaliseren van de verkoopfactuur, is het mogelijk om:
>
> - Ontvangsten via het bankafschrift af te letteren tegen de verkoopboeking
> - Een rapport op te vragen met openstaande posten
> - Het rapport debiteurenkaart op te vragen
> - De balans en winst- & verliesrekening bij te werken
> - Aan te manen
>
> Handige links:
>
> - Verkoopboekingen: <https://accounting.twinfield.com/webservices/documentation/#/ApiReference/Transactions/SalesTransactions>
> - Verkoopfacturen: <https://accounting.twinfield.com/webservices/documentation/#/ApiReference/Transactions/SalesInvoices>

## In short

| | Sales transaction (_verkoopboeking_) | Sales invoice (_verkoopfactuur_) |
|---|---|---|
| Who makes the invoice | Your own software | Twinfield, which can also send it |
| What Twinfield gets | The booking: customer, revenue, VAT | Customer, articles, quantities |
| In the general ledger | Right after the import | After the invoice is final (_gejournaliseerd_) |
| Request | `<transaction>` with day book `VRK` | `<salesinvoice>` |
| Module | – | The Classic invoicing module only, not the new sales module (the blue tiles) |

Either way, the result is a sales transaction in the general ledger. You can then match payments against it, report open items, and send reminders.

The links in the article go to the old API reference. The current documentation is on the Twinfield developer portal: [sales transactions](https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/) and [sales invoices](https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/).

## With this SDK

The SDK has no resources for sales transactions and sales invoices yet. Send them with [`processXml()`](/guides/process-xml/) and build the XML with `XmlBuilder`, so values are escaped.

### Sales transaction

The invoice is made in your own software: send a `<transaction>` with a total line for the customer (including VAT) and a detail line per revenue account (without VAT). Twinfield adds the VAT lines. See [Transactions](/guides/transactions/#how-a-transaction-is-built) for the debit and credit sides.

```php
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

$xml = XmlBuilder::create('transaction', ['destiny' => 'temporary']);

$header = $xml->add($xml->root(), 'header');
$xml->add($header, 'office', '1000');
$xml->add($header, 'code', 'VRK');
$xml->add($header, 'date', XmlValue::date(new DateTimeImmutable('2026-11-09')));
$xml->add($header, 'invoicenumber', '2026-0042');

$lines = $xml->add($xml->root(), 'lines');

// The customer owes 121.00 including VAT.
$total = $xml->add($lines, 'line', attributes: ['type' => 'total', 'id' => '1']);
$xml->add($total, 'dim2', '1000');
$xml->add($total, 'debitcredit', 'debit');
$xml->add($total, 'value', '121.00');

// 100.00 revenue without VAT, with 21% VAT (VH).
$detail = $xml->add($lines, 'line', attributes: ['type' => 'detail', 'id' => '2']);
$xml->add($detail, 'dim1', '8000');
$xml->add($detail, 'debitcredit', 'credit');
$xml->add($detail, 'value', '100.00');
$xml->add($detail, 'vatcode', 'VH');

$result = $twinfield->processXml($xml->toXml());

echo 'VRK ', $result->xpath()->evaluate('string(/transaction/header/number)'), PHP_EOL;
```

`destiny="temporary"` saves it as provisional, so you can still check it in Twinfield. Use `destiny="final"` to put it in the general ledger at once, as the article describes.

Full example, with the VAT calculated from the VAT code and a dry run: [Create sales transaction](/examples/process-xml/create-sales-transaction/).

### Sales invoice

Twinfield makes the invoice: send a `<salesinvoice>` with an invoice type, the customer and a line per article. Twinfield calculates the amounts and VAT. With status `concept` the invoice can still be changed. With `final` Twinfield books the sales transaction and returns it in `financials`.

```php
$xml = XmlBuilder::create('salesinvoice');

$header = $xml->add($xml->root(), 'header');
$xml->add($header, 'office', '1000');
$xml->add($header, 'invoicetype', 'FACTUUR');
$xml->add($header, 'customer', '1000');
$xml->add($header, 'status', 'concept');
$xml->add($header, 'paymentmethod', 'bank');

$line = $xml->add($xml->add($xml->root(), 'lines'), 'line', attributes: ['id' => '1']);
$xml->add($line, 'article', '0');
$xml->add($line, 'quantity', '1');
$xml->add($line, 'units', '1');
$xml->add($line, 'unitspriceexcl', '100.00');

$result = $twinfield->processXml($xml->toXml());

echo 'Invoice ', $result->xpath()->evaluate('string(/salesinvoice/header/invoicenumber)'), PHP_EOL;
```

Full example: [Create sales invoice](/examples/process-xml/create-sales-invoice/).

### After the booking

With the sales transaction in the general ledger, the SDK can already match a payment on the bank statement against it. Give the customer and invoice number to `receive()`. See [Split payment over invoices](/examples/bank-transactions/split-payment-over-invoices/).
