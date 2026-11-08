# Examples

Small, self-contained examples. Each folder shows one thing, with an `example.php` you can run and a `README.md` that explains it.

## Setup

1. Register `http://localhost:8765/callback` as redirect URI for your client in the [Twinfield developer portal](https://developers.twinfield.com).
2. Run any example, for instance:

   ```sh
   php examples/offices/list-offices/example.php
   ```

The first time, the example asks for your client ID, client secret and redirect URI, opens the Twinfield login in your browser and asks for a default company. Everything is saved in `~/.config/twinfield/`, so after that examples start right away. Run `composer twinfield:setup` to change these values.

Every example starts the same way:

```php
require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
```

In your own application you create the client yourself: see [Authentication](getting-started/authentication/).

Add `TWINFIELD_DEBUG=1` to any example to see the SOAP requests and responses.

## Getting started

- [Login](getting-started/login/): let a user log in and get the refresh token.
- [Authentication](getting-started/authentication/): create a client in your own application.
- [Token info](getting-started/token-info/): organisation, user and cluster of an access token.
- [Error handling](getting-started/error-handling/): catch errors and act on their code.

## Offices

- [List offices](offices/list-offices/): all offices the user has access to.
- [Switch company](offices/switch-company/): the same call for several companies.

## Activities

- [List activities](activities/list-activities/): code and name of all activities.
- [Search activities](activities/search-activities/): search by name, change date or group.
- [Read activity](activities/read-activity/): all settings of one activity.
- [Create activity](activities/create-activity/): create an activity (changes data).
- [Update activity](activities/update-activity/): change an existing activity (changes data).
- [Delete activity](activities/delete-activity/): delete or hide an activity (changes data).

## Asset methods

- [List asset methods](asset-methods/list-asset-methods/): code and name of all asset methods, or those matching a pattern.
- [Read asset method](asset-methods/read-asset-method/): calculation, accounts and free text fields of one asset method.
- [Create asset method](asset-methods/create-asset-method/): create an asset method (changes data).
- [Update asset method](asset-methods/update-asset-method/): change an existing asset method (changes data).
- [Delete asset method](asset-methods/delete-asset-method/): delete or hide an asset method (changes data).

## Bank books

- [List bank books](bank-books/list-bank-books/): code and name of all bank books, or those matching a pattern.
- [Read bank book](bank-books/read-bank-book/): bank account, ledger account and automatic bank processing of one bank book.
- [Create bank book](bank-books/create-bank-book/): create a bank book with a bank account (changes data; cannot be deleted).
- [Update bank book](bank-books/update-bank-book/): change a bank book; only changed values are sent (changes data).

## Bank transactions

- [Read bank transaction](bank-transactions/read-bank-transaction/): header, lines and matches of one bank transaction.
- [Create bank transaction](bank-transactions/create-bank-transaction/): a bank statement with calculated total line and closing balance (changes data).
- [Update bank transaction](bank-transactions/update-bank-transaction/): change a provisional transaction with `edit()` (changes data).
- [Delete bank transaction](bank-transactions/delete-bank-transaction/): delete a provisional transaction (changes data).
- [Post bank transaction](bank-transactions/post-bank-transaction/): make a provisional transaction final (changes data; cannot be undone).
- [Import bank statement](bank-transactions/import-bank-statement/): a CSV statement to one transaction, with rules per line and a balance check.
- [Bank costs with VAT](bank-transactions/bank-costs-with-vat/): split an amount including VAT, exactly.
- [Split payment over invoices](bank-transactions/split-payment-over-invoices/): one payment for several open invoices.
- [Bulk import with errors](bank-transactions/bulk-import-with-errors/): save several transactions at once and handle the ones that fail (changes data).

## Cash books

- [List cash books](cash-books/list-cash-books/): code and name of all cash books, or those matching a pattern.
- [Read cash book](cash-books/read-cash-book/): name, short name and ledger account of one cash book.
- [Create cash book](cash-books/create-cash-book/): create a cash book (changes data; cannot be deleted).
- [Update cash book](cash-books/update-cash-book/): change a cash book; only changed values are sent (changes data).

## Cash transactions

- [Read cash transaction](cash-transactions/read-cash-transaction/): header and lines of one cash transaction.
- [Daily cash register](cash-transactions/daily-cash-register/): sales per VAT rate, expenses and the cash difference of one day.

## Cost centers

- [List cost centers](cost-centers/list-cost-centers/): code and name of all cost centers, or those matching a pattern.
- [Read cost center](cost-centers/read-cost-center/): name, status and use of one cost center.
- [Create cost center](cost-centers/create-cost-center/): create a cost center (changes data).
- [Update cost center](cost-centers/update-cost-center/): rename an existing cost center (changes data).
- [Delete cost center](cost-centers/delete-cost-center/): delete or hide a cost center, and activate it again (changes data).

## Currencies

- [List currencies](currencies/list-currencies/): code and name of all currencies, or those matching a pattern.
- [Read currency](currencies/read-currency/): short name, status and exchange rates of one currency, and the rate today.
- [Create currency](currencies/create-currency/): create a currency with exchange rates (changes data).
- [Update currency rates](currencies/update-currency-rates/): add, change and remove exchange rates (changes data).
- [Delete currency](currencies/delete-currency/): delete a currency (changes data).

## Customers

- [List customers](customers/list-customers/): code and name of all customers, or those matching a pattern.
- [Read customer](customers/read-customer/): payment term, direct debit, credit management, addresses and bank accounts of one customer.
- [Create customer](customers/create-customer/): create a customer with an address and a bank account (changes data).
- [Update customer](customers/update-customer/): change settings, an address, and add an address and a bank account (changes data).
- [Delete customer](customers/delete-customer/): delete or hide a customer, and activate it again (changes data).

## Dimension groups

- [List dimension groups](dimension-groups/list-dimension-groups/): code and name of all dimension groups, or those of one dimension type.
- [Read dimension group](dimension-groups/read-dimension-group/): short name, status and the dimensions of one group.
- [Create dimension group](dimension-groups/create-dimension-group/): create a group of customers (changes data).
- [Update dimension group](dimension-groups/update-dimension-group/): rename a group, and add and remove dimensions (changes data).
- [Delete dimension group](dimension-groups/delete-dimension-group/): delete a group (changes data).

## Dimension types

- [List dimension types](dimension-types/list-dimension-types/): code, name, mask and levels of all dimension types, or those of one financial level.
- [Read dimension type](dimension-types/read-dimension-type/): mask, levels and address labels of one type, and whether a code matches the mask.
- [Update dimension type](dimension-types/update-dimension-type/): change an address label (changes data).

## Fixed assets

- [List fixed assets](fixed-assets/list-fixed-assets/): code and name of all fixed assets, or those matching a pattern or in a group.
- [Read fixed asset](fixed-assets/read-fixed-asset/): status, depreciation settings, free texts, transaction lines and locked settings of one asset.
- [Create fixed asset](fixed-assets/create-fixed-asset/): create a fixed asset with the next free code (changes data).
- [Update fixed asset](fixed-assets/update-fixed-asset/): change a fixed asset; locked settings are left out (changes data).
- [Delete fixed asset](fixed-assets/delete-fixed-asset/): delete or hide a fixed asset, and activate it again (changes data).

## Budgets

- [List budgets](budgets/list-budgets/): code and name of all budgets, or those matching a pattern.
- [Budget vs. actual](budgets/budget-vs-actual/): budget and actual amounts per general ledger account, with the difference.
- [Budget by cost center](budgets/budget-by-cost-center/): amounts per account, cost center and period.
- [Budget by project](budgets/budget-by-project/): amounts per period, account, cost center and project.

## Finder

- [Search dimensions](finder/search-dimensions/): search with pattern, options and limit.
- [Paginate](finder/paginate/): go through all results page by page.

## Periods

- [List periods](periods/list-periods/): years of the company and the periods of a year.
- [Rename period](periods/rename-period/): change a period with a command (changes data).

## ProcessXml

- [List companies](process-xml/list-companies/): all companies, with a request without company.
- [Read dimension](process-xml/read-dimension/): read a customer and handle errors.
