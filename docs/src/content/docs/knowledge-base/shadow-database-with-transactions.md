---
title: Keeping a shadow database with transactions
description: "Twinfield knowledge base: download the transactions once with browse code 030_3, then only the changes, and check the result with 040_1, with this SDK."
---

When your software needs the transactions of a company, Twinfield asks you to keep a copy in your own database (a shadow database) instead of downloading them again and again: download everything once, then only the changes, and check the copy now and then. This article from the Twinfield community describes the three steps.

## The article

**Source:** [API Best Practices - Keeping a shadow database with transactions from Twinfield](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Keeping-a-shadow-database-with-transactions-from-Twinfield?language=en_US), Twinfield community (Wolters Kluwer). Quoted on 9 November 2026. The article has screenshots of the criteria and a Postman collection as attachment; the screenshots are described between square brackets.

> This article is part of this article serie: [API Best Practices - Introduction](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Introduction?language=en_US)
>
> In this article we'll explain how you can download transactions from Twinfield and keep a shadow database in your own software. We ask you to follow this best practice because it supports our Fair Use Policy. You can read about our Fair Use Policy here: [https://accounting.twinfield.com/webservices/documentation/#/GettingStarted/FUP](https://accounting.twinfield.com/webservices/documentation/#/GettingStarted/FUP)
>
> **Good to know:** There is a Postman-collection in the attachment of this article with ready to use examples of the browse codes and queries that are mentioned in this article.
>
> The process of keeping a shadow database in your own software has three steps:
>
> 1. Download the intial load only once
> 2. Download the incremental load daily or weekly
> 3. Validate if your shadow database is correct
>
> During the initial load you will request all the transactions from Twinfield that you need. This is your starting point. After you have done this, you will continue by requesting only the changes since the initial load. This is called the incremental load. You can do the incremental load daily or weekly depending on your needs.
>
> ### The initial load
>
> For the initial load you can use browse code 030\_3. Please refer to the Postman collection in the attachment of this article for a ready to use example of this browse code. Please refer to this article for the complete documentation about browse codes: [https://accounting.twinfield.com/webservices/documentation/#/ApiReference/Request/BrowseData](https://accounting.twinfield.com/webservices/documentation/#/ApiReference/Request/BrowseData)
>
> **Important:** Decide which columns of the browse you want to use. Remove all columns that are not relevant to you. The purpose of this is to reduce the amount of data that is being downloaded via the API.
>
> You will download all the transactions that you need during the initial load, but please limit your downloads to one book year at a time. If you need the years 2020, 2021 and 2022, then download 2020 first, then 2021 and then 2022.
>
> If you notice that you receive a timeout while downloading one book year at a time, then the company has too many transactions. In this case you'll have to split up your downloads even more. In this case you can split up 2020 in periods. First download 2020/00, then 2020/01, and so forth.
>
> You can use the column fin.trs.head.yearperiod to set the criteria for this. If you want the entire book year 2020 then ask for the periods 2020/00 until 2020/55. If you only want one period at a time then ask for 2020/00 until 2020/00.
>
> [Screenshot: the criteria of the request on `fin.trs.head.yearperiod`.]
>
> If you want to limit the transactions you download to only profit-and-loss ledgers or to only bank statements then you can apply additional criteria to limit the amount of transactions that you download. The table below will show the most interesting criteria.
>
> | Field | Explanation |
> |---|---|
> | fin.trs.head.yearperiod | If you want the entire book year 2020 then ask for the periods 2020/00 until 2020/55. If you only want one period at a time then ask for 2020/00 until 2020/00.<br><br>For example:<br>`<operator>between</operator>`<br>`<from>2020/01</from>`<br>`<to>2022/01</to>` |
> | fin.trs.head.code | If you only want bank statements or sales invoices then you can limit your request to a specific transaction type.<br><br>For example:<br>`<operator>equal</operator>`<br>`<from>SLS</from>`<br>`<to></to>` |
> | fin.trs.line.dim1 | If you only want transaction lines for specific ledgers then you can limit your request to specific ledgers.<br><br>For example:<br>`<operator>between</operator>`<br>`<from>0100</from>`<br>`<to>0200</to>` |
>
> ### The incremental load
>
> After you have downloaded the initial load you can set up a daily or weekly request that only downloads transaction that were added or changed since your last download. You can use the same request as you used for the initial load, but this time you will add this extra criteria:
>
> [Screenshot: an extra criterion on the date and time of the last change.]
>
> This will limit the transactions that you download to only those transactions that were added or changed within the time range that you specifiy. The date/time format is "yyyymmddhhmmss". 20220428000000 means 28 April 2022 at 00:00:00 hours.
>
> **Good to know:** The API only uses universal time (UTC). You can check the current time in UTC here: [https://time.is/utc](https://time.is/utc)
>
> The next step is to update your shadow database by adding the new transactions and updating the modified existing transactions.
>
> The last step of the incremental load is to request a list of transactions that were deleted since your last download. You can use the query GetDeletedTransactions for this. You'll find a ready to use example in the Postman collection in the attachment. Update your shadow database by deleting those transactions in your shadow database.
>
> [Screenshot: the GetDeletedTransactions query with a date range.]
>
> For this query you also have to specify the time range. The date/time format is "yyyy-mm-ddThh:mm:ss.miliseconds" 2022-04-28T00:00:00.000 means 28 April 2022 at 00:00:00.000 hours.
>
> ### Validate your shadow database
>
> After your shadow database is up to date, it's important to validate that your data matches the data in Twinfield. You can do this by downloading the balance per ledger from Twinfield. You can use browse code 040\_1 for this.
>
> After you've downloaded the total value per ledger by using browse code 040\_1, you can compare it to the total value per ledger in your shadow database.
>
> Limit your downloads to one year at a time. If you only want to retrieve the balances for 2020 then use the criteria below.
>
> **Good to know:** You can also use the other criteria of this browse code to limit the number of ledgers for which you want to view the balance.
>
> [Screenshot: the criteria of browse code 040_1 for the year 2020.]
>
> After you have downloaded the balance per ledger from Twinfield you can calculate the balance per ledger in your own shadow database. If everything is correct then your shadow database should show the same balances as shown in browse code 040\_1.

## In short

1. **Initial load**: browse code 030_3, only the columns you need, one year at a time (`2020/00` to `2020/55`), or one period at a time when a year times out.
2. **Incremental load**: the same request with a criterion on the change date (`yyyymmddhhmmss`, UTC), and the deleted transactions with `GetDeletedTransactions`.
3. **Validate**: the balance per general ledger account with browse code 040_1, one year at a time, compared with the sum in your shadow database.

## With this SDK

Browse data is a fluent query: `$twinfield->browse('030_3')->columns(…)->between(…)->get()`. See the [browse data guide](/guides/browse-data/). The three steps are runnable examples, with a small SQLite database:

| Step | Example | What the SDK does |
|---|---|---|
| Initial load | [Initial load](/examples/shadow-database/initial-load/) | 030_3 per year with `between('fin.trs.head.yearperiod', '2025/00', '2025/55')`; each row has its key as `transactionLine` (day book, number, line) |
| Incremental load | [Incremental load](/examples/shadow-database/incremental-load/) | The same query with `betweenDateTimes('fin.trs.head.modified', $since, $until)`, which converts to UTC; deleted transactions with `deletedTransactions()->list(from: $since)` |
| Validate | [Validate balances](/examples/shadow-database/validate-balances/) | 040_1 per account with `fin.trs.line.basevaluesigned`, compared with `Amount`, so without rounding differences |

```php
use SolveBeam\Twinfield\BrowseData\BrowseCode;

$result = $twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)
    ->columns('fin.trs.head.code', 'fin.trs.head.number', 'fin.trs.line.dim1', 'fin.trs.line.valuesigned')
    ->between('fin.trs.head.yearperiod', '2025/00', '2025/55')
    ->equal('fin.trs.head.status', 'normal')
    ->get();

foreach ($result as $row) {
    $key = $row->transactionLine;   // day book, number and line: the key in your shadow database
}
```

What we found while building the examples, beyond the article:

- **Always send a status criterion.** Twinfield does not apply the proposed criteria of the browse definition: without `equal('fin.trs.head.status', 'normal')` you also get draft transactions, which all have number `-1` and can't be told apart.
- **The change date is a transaction field** (`fin.trs.head.modified`): the incremental load returns all lines of a changed transaction. A transaction can have more or fewer lines after a change, so replace all its lines in your database.
- **`fin.trs.head.yearperiod` can only be a criterion in 030_3**: as column it gives a server error. Use `fin.trs.head.year` and `fin.trs.head.period` as columns.
- **The deleted transactions filter on the day only**, while the change date has a time. Overlap the two windows; deleting a transaction twice does no harm.
- **Compare the same lines.** 040_1 needs a regime (`fin.trs.head.balanceregimeprompt`, `Generic` when left out); filter 030_3 on the same regime (`fin.trs.head.regime`) and status, or the balances differ for companies with regimes.
- **Large responses.** Twinfield returns all rows at once, without paging: a query over 216,710 lines was 25 MB and took 34 seconds. The SDK reads such responses completely; before, text over 10 MB was cut off by libxml without an error.
- **Key per organisation.** Company codes are only unique within an organisation: see [Keys for a shadow database](/guides/organisations/#keys-for-a-shadow-database).

The definitions of 030_3 and 040_1, with all fields and criteria, are in [`browse-definitions/`](https://github.com/solvebeam/php-twinfield/tree/main/browse-definitions) in the repository.
