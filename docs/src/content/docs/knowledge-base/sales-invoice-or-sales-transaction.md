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
| With this SDK | `$twinfield->salesTransactions()` | `$twinfield->salesInvoices()` |
| Module | – | The Classic invoicing module only, not the new sales module (the blue tiles) |

Either way, the result is a sales transaction in the general ledger. You can then match payments against it, report open items, and send reminders.

The links in the article go to the old API reference. The current documentation is on the Twinfield developer portal: [sales transactions](https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/) and [sales invoices](https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/).

## With this SDK

### Sales transaction

The invoice is made in your own software: `$twinfield->salesTransactions()` books it. `SalesTransactionInput` calculates the total line for the customer (including VAT) from the revenue lines (without VAT), so the transaction always balances. Twinfield adds the VAT lines. See [Sales transactions](/guides/transactions/#sales-transactions) in the transactions guide.

```php
use SolveBeam\Twinfield\SalesTransaction\SalesTransactionInput;

$date = new DateTimeImmutable('2026-11-09');

$transaction = $twinfield->salesTransactions()->create(
    SalesTransactionInput::invoice('VRK', customer: '1000', invoiceNumber: '2026-0042', date: $date)
        ->dueDate($date->modify('+30 days'))
        // 100.00 revenue without VAT; the VAT comes from the percentage of VH on the date.
        ->revenue('8000', '100.00', vatCode: $twinfield->vatCodes()->get('VH')),
);

echo $transaction->dayBook(), ' ', $transaction->number(), ': ', $transaction->value(), PHP_EOL;
```

New transactions are provisional, so you can still check them in Twinfield. Call `final()` on the input to put it in the general ledger at once, as the article describes.

Full examples: [Create sales transaction](/examples/sales-transactions/create-sales-transaction/) and [Credit note](/examples/sales-transactions/credit-note/).

### Sales invoice

Twinfield makes the invoice: `$twinfield->salesInvoices()` sends a `<salesinvoice>` with an invoice type, the customer and a line per article. Twinfield gives the invoice number and calculates the amounts and VAT. The invoice is a concept, so it can still be changed. Making it final is the _journaliseren_ of the article: Twinfield then books the sales transaction. See [Sales invoices](/guides/transactions/#sales-invoices) in the transactions guide.

```php
use SolveBeam\Twinfield\SalesInvoice\PaymentMethod;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceInput;

$invoices = $twinfield->salesInvoices();

$input = SalesInvoiceInput::create('FACTUUR', customer: '1000')
    ->paymentMethod(PaymentMethod::Bank)
    // The one-off item 0: your own description and price, without VAT.
    ->oneOff('Consultancy November', '100.00', vatCode: 'VH');

// Check and calculate it first, without saving.
echo 'Total: ', $invoices->calculate($input)->totals->valueInc, PHP_EOL;

$invoice = $invoices->create($input);
$invoice = $invoices->finalize($invoice->invoiceType, $invoice->invoiceNumber);

// The sales transaction in the general ledger.
$transaction = $invoices->salesTransaction($invoice);

echo 'Invoice ', $invoice->invoiceNumber, ' booked as ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
```

Call `final()` on the input to make the invoice final when you create it. A final invoice can't be changed, and the API can't delete sales invoices. As the article says, this only works for the Classic invoicing module: there is no web service for the new sales module (the blue tiles).

Full examples: [Create sales invoice](/examples/sales-invoices/create-sales-invoice/), [Finalize sales invoice](/examples/sales-invoices/finalize-sales-invoice/), [Read sales invoice](/examples/sales-invoices/read-sales-invoice/) and [List invoice types](/examples/sales-invoices/list-invoice-types/).

### After the booking

With the sales transaction in the general ledger, the SDK can match a payment on the bank statement against it: give the customer and invoice number to `receive()`. See [Split payment over invoices](/examples/bank-transactions/split-payment-over-invoices/). Unpaid invoices are in `salesTransactions()->openInvoices()`: see [Open invoices](/examples/sales-transactions/open-invoices/).
