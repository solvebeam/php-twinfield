---
title: API best practices
description: "Twinfield knowledge base: the introduction to Twinfield's API Best Practices series, and where to find each topic in this SDK."
---

Twinfield has a series of articles with best practices for building an API integration. This introduction lists all articles in the series: what to know before you start, how to connect, common integrations, importing and retrieving data, and the API limits.

## The article

**Source:** [API Best Practices - Introduction](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Introduction?language=en_US), Twinfield community (Wolters Kluwer). Quoted on 9 November 2026.

> This series guides you through the process of building your API integration with Twinfield. The purpose of these articles is to share best practices, help you understand key concepts, and highlight important considerations and potential pitfalls. We also explore common integration scenarios to provide practical examples and help you get started with popular use cases.
>
> **Things to know before you start**
>
> - [What does the certification process for Twinfield's API look like?](https://wktaaeu.my.site.com/nlcommunity/s/article/Wat-komt-er-aan-bod-tijdens-de-certificering?language=en_US)
> - [Why is the office code an important identifier?](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Why-is-the-office-code-an-important-identifier?language=en_US)
> - [How does matching work in Twinfield?](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-How-does-matching-work-in-Twinfield?language=en_US)
> - [How to determine the period table?](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-How-to-determine-the-period-table?language=en_US)
>
> **Setting up a connection to Twinfield**
>
> - [Setting up a connection using OAuth2](https://wktaaeu.my.site.com/nlcommunity/s/article/Setting-up-OAuth-2-0-Introduction?language=en_US)
> - [Retrieving a list of offices that you can access](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Retrieving-a-list-of-offices-that-you-can-access?language=en_US)
>
> **Common API connections**
>
> - [API Best Practices - POS (Point of Sale) system](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-POS-Point-of-Sale?language=en_US)
> - [Webshop](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Common-API-connections-Webshop?language=en_US)
> - [Medical professionals](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Common-API-connections-Medical-professionals?language=en_US)
>
> **Importing data into Twinfield**
>
> - [Importing sales transactions including customers](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Importing-sales-transactions?language=en_US)
> - [Importing purchase transactions including suppliers](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Importing-purchase-transactions-including-suppliers?language=en_US)
> - [Importing cash transactions](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Importing-cash-transactions?language=en_US)
> - [Importing journal transactions](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Importing-journal-transactions?language=en_US)
>
> **Retrieving data from Twinfield**
>
> - [Keeping a shadow database with customers/suppliers](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Keeping-a-shadow-database-with-customers-and-suppliers?language=en_US)
> - [Keeping a shadow database with transactions from Twinfield](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Keeping-a-shadow-database-with-transactions-from-Twinfield?language=en_US)
> - [Downloading open invoices from Twinfield](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Downloading-open-invoices-from-Twinfield-summary?language=en_US)
>
> **API Rate Limits**
>
> - [Information about Twinfield API Rate Limits](https://developers.twinfield.com/documentation/getting-started/fair-use-policy#rate-limits)
> - [How to adjust my Twinfield API connection to match the API limit on number of transaction lines?](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-How-to-adjust-my-Twinfield-API-connection-to-match-the-API-limit-on-number-of-transaction-lines?language=en_US)
> - [How to adjust my API connection to fit Twinfield API limits?](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-practices-How-to-adjust-my-API-connection-to-fit-Twinfield-API-limits?language=en_US)
>
> **Getting more help**
>
> - [How do I create an API-related support-case?](https://wktaaeu.my.site.com/nlcommunity/s/article/Hoe-maak-ik-een-case-aan-inzake-API-koppelingen?language=en_US)
> - [Link to the Support Portal (for Twinfield, Basecone, Alure Online)](https://wktaaeu.my.site.com/nlcommunity/s)

The links in the article are relative links on the community site; they are written out in full here.

## With this SDK

Where to find each topic of the series in this SDK:

| Topic | With this SDK |
|---|---|
| The office code as identifier | [Organisations](/guides/organisations/): every model knows its company and organisation, and [Keys for a shadow database](/guides/organisations/#keys-for-a-shadow-database) lists the key for each kind of data. Twinfield now calls an office a _company_. |
| Matching | [Matching](/guides/transactions/#matching) and [`receive()` and `pay()`](/guides/transactions/#receive-and-pay) in the transactions guide; example: [Split payment over invoices](/examples/bank-transactions/split-payment-over-invoices/) |
| The period table | [List periods](/examples/periods/list-periods/) and [Rename period](/examples/periods/rename-period/) |
| OAuth2 | [OAuth2](/guides/oauth2/); examples: [Authentication](/examples/getting-started/authentication/) and [Token info](/examples/getting-started/token-info/) |
| Offices you can access | [Offices](/guides/master-data/#offices); examples: [List offices](/examples/offices/list-offices/) and [Switch company](/examples/offices/switch-company/) |
| POS system | [Daily cash register](/examples/cash-transactions/daily-cash-register/) |
| Webshop | [Sales invoice or sales transaction?](/knowledge-base/sales-invoice-or-sales-transaction/), [Create customer](/examples/customers/create-customer/) and [Create sales transaction](/examples/sales-transactions/create-sales-transaction/) |
| Sales transactions including customers | [Sales transactions](/guides/transactions/#sales-transactions) and [Customers](/guides/master-data/#customers) |
| Purchase transactions including suppliers | [Purchase transactions](/guides/transactions/#purchase-transactions) and [Suppliers](/guides/master-data/#suppliers) |
| Cash transactions | [Daily cash register](/examples/cash-transactions/daily-cash-register/) and [Read cash transaction](/examples/cash-transactions/read-cash-transaction/) |
| Journal transactions | [Journal transactions](/guides/transactions/#journal-transactions); examples: [Create journal transaction](/examples/journal-transactions/create-journal-transaction/) and [Opening balance](/examples/journal-transactions/opening-balance/) |
| Shadow database with customers/suppliers | [Searching](/guides/master-data/#searching) in the master data guide; examples: [List customers](/examples/customers/list-customers/), [List suppliers](/examples/suppliers/list-suppliers/) and [Paginate](/examples/finder/paginate/) |
| Shadow database with transactions | [Reading](/guides/transactions/#reading) and [Deleted transactions](/guides/transactions/#deleted-transactions); example: [List deleted transactions](/examples/deleted-transactions/list-deleted-transactions/) |
| Open invoices | [Open sales invoices](/examples/sales-transactions/open-invoices/) and [Open purchase invoices](/examples/purchase-transactions/open-invoices/) |
| Rate limits | [Rate limits](/guides/rate-limits/), and see below |
| Many transaction lines | [Saving many transactions](/guides/transactions/#saving-many-transactions); example: [Bulk import with errors](/examples/bank-transactions/bulk-import-with-errors/) |

### Rate limits

When Twinfield answers HTTP 429 Too Many Requests, the SDK throws a `RateLimitExceededException`. It does not retry by itself: your integration decides when to try again. `retryAfterSeconds` has the value of the `Retry-After` header, or `null` when Twinfield did not send one. `concurrencyLimitExceeded` says whether the concurrency limit was exceeded instead of the rate limit. See [Rate limits](/guides/rate-limits/#with-this-sdk) for the other properties and exponential backoff.

```php
use SolveBeam\Twinfield\Soap\RateLimitExceededException;

try {
    $customer = $twinfield->customers()->get('1000');
} catch (RateLimitExceededException $e) {
    sleep($e->retryAfterSeconds ?? 60);

    $customer = $twinfield->customers()->get('1000');
}
```

See also [Error handling](/examples/getting-started/error-handling/).
