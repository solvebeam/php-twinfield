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
- [REST API request](getting-started/rest-request/): call an endpoint of the REST API directly, with the company GUID.
- [Rate limit](getting-started/rate-limit/): read what a request cost and how many API credits are left.

## Offices

- [List offices](offices/list-offices/): all offices the user has access to.
- [Search offices](offices/search-offices/): search by code or name, or only offices without tax group.
- [Read office](offices/read-office/): all settings of one office.
- [Switch company](offices/switch-company/): the same call for several companies.

## Activities

- [List activities](activities/list-activities/): code and name of all activities.
- [Search activities](activities/search-activities/): search by name, change date or group.
- [Read activity](activities/read-activity/): all settings of one activity.
- [Create activity](activities/create-activity/): create an activity (changes data).
- [Update activity](activities/update-activity/): change an existing activity (changes data).
- [Delete activity](activities/delete-activity/): delete or hide an activity (changes data).

## Asset methods (classic)

- [List asset methods](classic-asset-methods/list-asset-methods/): code and name of all asset methods, or those matching a pattern.
- [Read asset method](classic-asset-methods/read-asset-method/): calculation, accounts and free text fields of one asset method.
- [Create asset method](classic-asset-methods/create-asset-method/): create an asset method (changes data).
- [Update asset method](classic-asset-methods/update-asset-method/): change an existing asset method (changes data).
- [Delete asset method](classic-asset-methods/delete-asset-method/): delete or hide an asset method (changes data).

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

## Sales transactions

- [Read sales transaction](sales-transactions/read-sales-transaction/): invoice number, customer, revenue and VAT lines, payment status and payments of one sales transaction.
- [Create sales transaction](sales-transactions/create-sales-transaction/): book an invoice from your own software, with the VAT calculated from the VAT code (changes data with `--save`).
- [Credit note](sales-transactions/credit-note/): book a credit note: the same positive amounts, the sides turned around (changes data with `--save`).
- [Open invoices](sales-transactions/open-invoices/): unpaid invoices of a customer, with Finder `IVT`.

## Purchase transactions

- [Read purchase transaction](purchase-transactions/read-purchase-transaction/): invoice number, supplier, cost and VAT lines, payment status and payments of one purchase transaction.
- [Create purchase transaction](purchase-transactions/create-purchase-transaction/): book a received invoice, with the VAT calculated from the VAT code (changes data with `--save`).
- [Credit note](purchase-transactions/credit-note/): book a credit note of a supplier: the same positive amounts, the sides turned around (changes data with `--save`).
- [Open invoices](purchase-transactions/open-invoices/): unpaid invoices of a supplier, with Finder `IVT`.

## Journal transactions

- [Read journal transaction](journal-transactions/read-journal-transaction/): header, regime, and detail and VAT lines of one journal transaction.
- [Create journal transaction](journal-transactions/create-journal-transaction/): book a correction; the lines balance by themselves, without total line (changes data with `--save`).
- [Opening balance](journal-transactions/opening-balance/): book an opening balance in period 0 (changes data with `--save`).

## Reversal transactions

- [Read reversal](reversal-transactions/read-reversal/): the reversed transaction and reversal settings of a journal transaction.
- [Reverse journal transaction](reversal-transactions/reverse-journal-transaction/): reverse a final journal transaction (changes data).

## Sales invoices

- [List invoice types](sales-invoices/list-invoice-types/): the invoice types of the company, with Finder `INV`.
- [Read sales invoice](sales-invoices/read-sales-invoice/): header, lines, VAT and totals of one sales invoice, and the sales transaction Twinfield booked for it.
- [Create sales invoice](sales-invoices/create-sales-invoice/): let Twinfield make a concept invoice, or only calculate it (changes data with `--save`).
- [Finalize sales invoice](sales-invoices/finalize-sales-invoice/): make a concept invoice final, so Twinfield books the sales transaction (changes data with `--save`).

## Deleted transactions

- [List deleted transactions](deleted-transactions/list-deleted-transactions/): what was deleted in the last days, by whom and why, of all day books.

## Electronic bank statements

- [Import electronic bank statement](electronic-bank-statements/import-electronic-bank-statement/): a CSV statement as the bank reports it; Twinfield allocates the lines (changes data with `--save`).
- [List electronic bank statements](electronic-bank-statements/list-electronic-bank-statements/): the statements of the last days with their lines, and how Twinfield allocated and matched them.

## Transaction blocked values

- [Register blocked value](transaction-blocked-values/register-blocked-value/): block part of a line of an existing transaction, e.g. the total line of a purchase invoice (changes data).
- [Unregister blocked value](transaction-blocked-values/unregister-blocked-value/): remove the blocked value from that line again (changes data).

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
- [List changed customers](customers/list-changed-customers/): customers changed since a date, also deleted and archived ones (REST API).

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

The new fixed assets module, on the REST API. For the classic module see [Fixed assets (classic)](#fixed-assets-classic).

- [List fixed assets](fixed-assets/list-fixed-assets/): status, book value and last depreciated period of all assets.
- [Read fixed asset](fixed-assets/read-fixed-asset/): depreciation per regime, class and history of one asset.

## Fixed assets (classic)

- [List fixed assets](classic-fixed-assets/list-fixed-assets/): code and name of all fixed assets, or those matching a pattern or in a group.
- [Read fixed asset](classic-fixed-assets/read-fixed-asset/): status, depreciation settings, free texts, transaction lines and locked settings of one asset.
- [Create fixed asset](classic-fixed-assets/create-fixed-asset/): create a fixed asset with the next free code (changes data).
- [Update fixed asset](classic-fixed-assets/update-fixed-asset/): change a fixed asset; locked settings are left out (changes data).
- [Delete fixed asset](classic-fixed-assets/delete-fixed-asset/): delete or hide a fixed asset, and activate it again (changes data).

## General ledger accounts

- [List general ledger accounts](general-ledger-accounts/list-general-ledger-accounts/): balance sheet or profit and loss accounts, hidden ones included.
- [Read general ledger account](general-ledger-accounts/read-general-ledger-account/): sub-analysis, child validations, VAT code and groups of one account.
- [Create general ledger account](general-ledger-accounts/create-general-ledger-account/): create a profit and loss account that needs a cost center (changes data).
- [Update general ledger account](general-ledger-accounts/update-general-ledger-account/): make an account matchable and change its child validations (changes data).
- [Delete general ledger account](general-ledger-accounts/delete-general-ledger-account/): delete or hide an account, and activate it again (changes data).

## Ledger accounts

- [List ledger accounts](ledger-accounts/list-ledger-accounts/): balance sheet and profit and loss accounts with their GUID (REST API).

## Posting rules

- [List posting rules](posting-rules/list-posting-rules/): how invoices of customers and suppliers are divided over accounts (REST API).

## Projects

- [List projects](projects/list-projects/): code and name of all projects.
- [Search projects](projects/search-projects/): search by name, change date or group.
- [Read project](projects/read-project/): all settings of one project.
- [Create project](projects/create-project/): create a project (changes data).
- [Update project](projects/update-project/): change an existing project (changes data).
- [Delete project](projects/delete-project/): delete or hide a project (changes data).

## Rates

- [List rates](rates/list-rates/): code and name of all rates, or those matching a pattern.
- [Search rates](rates/search-rates/): rates for hours or quantities, by name.
- [Read rate](rates/read-rate/): type, unit, currency, all prices and the price today.
- [Create rate](rates/create-rate/): create a kilometre rate (changes data).
- [Update rate price](rates/update-rate-price/): new prices from a date with `newPriceFrom()` (changes data).
- [Delete rate](rates/delete-rate/): delete or hide a rate (changes data).

## Users

- [List users](users/list-users/): code and name of all users of the organisation.
- [Search users](users/search-users/): search by code, and the users that share a company with you.
- [Read user](users/read-user/): role, login status, companies and Time & Expenses settings of one user.
- [Current user](users/current-user/): the user of the access token, with `userCode()` and `users()->current()`.
- [Create user](users/create-user/): create and invite a user (changes data, can cost money).
- [Update user](users/update-user/): change a user with `edit()`, and grant or revoke company access (changes data).
- [Delete user](users/delete-user/): delete or hide a user with a reason (changes data).

## Suppliers

- [List suppliers](suppliers/list-suppliers/): code and name of all suppliers, or those matching a pattern.
- [Read supplier](suppliers/read-supplier/): payment term, how the supplier is paid, remittance advice, addresses, bank accounts and posting rules of one supplier.
- [Create supplier](suppliers/create-supplier/): create a supplier paid by credit transfer, with an address and a bank account (changes data).
- [Update supplier](suppliers/update-supplier/): change how the supplier is paid, a bank account, and add a bank account (changes data).
- [Delete supplier](suppliers/delete-supplier/): delete or hide a supplier, and activate it again (changes data).

## VAT codes

- [List VAT codes](vat-codes/list-vat-codes/): code and name of all VAT codes, or those matching a pattern.
- [Search VAT codes](vat-codes/search-vat-codes/): VAT codes for sales or purchases, by name.
- [Read VAT code](vat-codes/read-vat-code/): percentages over time, the accounts and VAT groups, and the VAT on an amount today.
- [Create VAT code](vat-codes/create-vat-code/): create a sales VAT code of 21% (changes data).
- [Update VAT percentage](vat-codes/update-vat-percentage/): a new rate from a date with `newPercentageFrom()` (changes data).
- [Delete VAT code](vat-codes/delete-vat-code/): delete or hide a VAT code (changes data).
- [List VAT code rates](vat-codes/list-vat-code-rates/): the rate on a date and the accounts of each VAT code (REST API).

## Sales articles

- [List sales articles](sales-articles/list-sales-articles/): prices, VAT code and translations of the articles (REST API).
- [Create sales article](sales-articles/create-sales-article/): create, update and delete an article (REST API, changes data).

## Budgets

- [List budgets](budgets/list-budgets/): code and name of all budgets, or those matching a pattern.
- [Budget vs. actual](budgets/budget-vs-actual/): budget and actual amounts per general ledger account, with the difference.
- [Budget by cost center](budgets/budget-by-cost-center/): amounts per account, cost center and period.
- [Budget by project](budgets/budget-by-project/): amounts per period, account, cost center and project.
- [List budget lines](budgets/list-budget-lines/): the budgets per year and their lines (REST API).
- [Create budget](budgets/create-budget/): create a budget, spread an amount over the periods and delete it again (REST API, changes data).

## Bank feeds

- [List PSD2 feeds](bank-feeds/list-psd2-feeds/): the PSD2 feeds of all companies and when their consent expires (REST API).

## Browse data

One example per browse code; see [Browse codes](https://php.twinfield.solvebeam.com/guides/browse-codes/) for all codes and their fields.

- [Read browse definition](browse-data/read-browse-definition/): the fields of a browse code and which can be a criterion.
- [List browse fields](browse-data/list-browse-fields/): data types and the values of fields with a fixed list.
- [General ledger transactions (000)](browse-data/000-general-ledger-transactions/): the lines of a general ledger account.
- [Transactions still to be matched (010)](browse-data/010-transactions-to-be-matched/): open lines on accounts with matching.
- [Transaction list (020)](browse-data/020-transaction-list/): the lines as entered, per day book, with input date and user.
- [General ledger details (030_1)](browse-data/030_1-general-ledger-details-v1/): every transaction line, first version.
- [General ledger details (030_2)](browse-data/030_2-general-ledger-details-v2/): every transaction line with the relation and regime.
- [General ledger details (030_3)](browse-data/030_3-general-ledger-details/): every transaction line with all details.
- [General ledger intercompany (031)](browse-data/031-general-ledger-intercompany/): intercompany lines with the company on the other side.
- [General ledger intercompany (031_2)](browse-data/031_2-general-ledger-intercompany-v2/): intercompany lines, with a reporting structure as criterion.
- [Annual report totals (040_1)](browse-data/040_1-annual-report-totals/): the balance per general ledger account.
- [Annual report year to date (050_1)](browse-data/050_1-annual-report-ytd/): the balance per account up to a period.
- [Annual report multicurrency (060)](browse-data/060-annual-report-multicurrency/): totals per account and currency.
- [Customer transactions (100)](browse-data/100-customer-transactions/): open items of customers.
- [Customers (130_1)](browse-data/130_1-customers/): customer lines with payment status and credit limit.
- [Customers (130_2)](browse-data/130_2-customers-v2/): customer lines changed since a moment.
- [Customers (130_3)](browse-data/130_3-customers-v3/): open items per credit manager.
- [Credit management (164)](browse-data/164-credit-management/): credit management actions on open invoices.
- [Supplier transactions (200)](browse-data/200-supplier-transactions/): open items of suppliers.
- [Suppliers (230_1)](browse-data/230_1-suppliers/): supplier lines with payment status.
- [Suppliers (230_2)](browse-data/230_2-suppliers-v2/): open items with their payment run.
- [Project transactions (300)](browse-data/300-project-transactions/): the lines of a project.
- [Asset transactions (301)](browse-data/301-asset-transactions/): the lines of a fixed asset.
- [Fixed assets (302_1)](browse-data/302_1-fixed-assets/): asset lines with the depreciation settings.
- [Cash transactions (400)](browse-data/400-cash-transactions/): the lines of a cash book per statement.
- [Bank transactions (410)](browse-data/410-bank-transactions/): the lines of a bank book per statement.
- [Time & Expenses totals (610_1)](browse-data/610_1-time-and-expenses-totals/): totals per project, activity, rate and user.
- [Time & Expenses multicurrency (620)](browse-data/620-time-and-expenses-multicurrency/): general ledger totals per project and currency.
- [Time & Expenses details (650_1)](browse-data/650_1-time-and-expenses-details/): every Time & Expenses entry.
- [Time & Expenses totals per week (651_1)](browse-data/651_1-time-and-expenses-totals-per-week/): hours and expenses per week.
- [Time & Expenses totals per period (652_1)](browse-data/652_1-time-and-expenses-totals-per-period/): hours and expenses per period.
- [Time & Expenses billing details (660_1)](browse-data/660_1-time-and-expenses-billing-details/): entries with invoice proposal and invoice.
- [Time & Expenses billing per week (661_1)](browse-data/661_1-time-and-expenses-billing-per-week/): value and proposed amount per week.
- [Time & Expenses billing per period (662_1)](browse-data/662_1-time-and-expenses-billing-per-period/): value and proposed amount per period.
- [Transaction summary (670)](browse-data/670-transaction-summary/): the number of transactions and lines per day book and period.
- [Bank link details (680)](browse-data/680-bank-link-details/): the bank books with IBAN and bank link status.
- [Bank link details (680_2)](browse-data/680_2-bank-link-details-v2/): the bank books with the kind of link and consent.
- [VAT return status (690)](browse-data/690-vat-return-status/): the VAT and ICT returns of a year with their status.
- [Hierarchy access (700)](browse-data/700-hierarchy-access/): who has access to which reporting structure.
- [Cost centers (900)](browse-data/900-cost-centers/): the lines per cost centre.

## Shadow database

- [Initial load](shadow-database/initial-load/): all transaction lines once, one year at a time.
- [Incremental load](shadow-database/incremental-load/): the changed and deleted transactions since the last sync.
- [Validate balances](shadow-database/validate-balances/): compare the balance per account with Twinfield.

## Declarations

- [List declarations](declarations/list-declarations/): VAT, ICT and tax group returns of the company or the organisation.
- [Download VAT return](declarations/download-vat-return/): a VAT return as XBRL, with its payment reference.

## Hierarchies

- [List hierarchies](hierarchies/list-hierarchies/): code and name of the reporting structures of the organisation.
- [Print hierarchy tree](hierarchies/print-hierarchy-tree/): nodes and accounts as an ASCII tree with `RecursiveTreeIterator`.
- [Accounts of a node](hierarchies/node-accounts/): the accounts of a node and of the nodes below it.
- [Save hierarchy](hierarchies/save-hierarchy/): create one with nodes, accounts and access rights, or change one (write).
- [Bind accounts](hierarchies/bind-accounts/): bind an account to a node and unbind it again (write).
- [Delete hierarchy](hierarchies/delete-hierarchy/): delete or hide one (write).

## Matching

- [Match transactions](matching/match-transactions/): match an invoice with its payment, partly or with a discount (write).
- [List match sets](matching/list-match-sets/): the matched lines of a customer with their match number (browse data).
- [Undo match](matching/undo-match/): undo a match set by its match number (write).
- [Matching audit trail](matching/matching-audit-trail/): the matches and undone matches since a date (REST API).

## Pay & Collect

- [List pay runs](pay-and-collect/list-pay-runs/): the payment and direct debit runs of a company.
- [Get pay run](pay-and-collect/get-pay-run/): a run with its summaries, bank accounts and invoices.
- [Create pay run](pay-and-collect/create-pay-run/): select the open invoices for a payment or direct debit run (write).
- [Finish pay run](pay-and-collect/finish-pay-run/): delete a run that is not posted yet, or end it after the files (write).
- [Pay run lifecycle](pay-and-collect/pay-run-lifecycle/): modify, authorise, post, create the SEPA files and finish (write, can't be undone).

## Finder

- [Search dimensions](finder/search-dimensions/): search with pattern, options and limit.
- [Paginate](finder/paginate/): go through all results page by page.
- [Search in all companies](finder/all-companies/): one search over all companies, with Finder option `company=*`.

## Periods

- [List periods](periods/list-periods/): years of the company and the periods of a year.
- [Period of transaction](periods/period-of-transaction/): name of the period of each transaction, and whether it is open.
- [Rename period](periods/rename-period/): change a period with a command (changes data).

## ProcessXml

- [List companies](process-xml/list-companies/): all companies, with a request without company.
- [Read dimension](process-xml/read-dimension/): read a customer and handle errors.
