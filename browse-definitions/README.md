# Twinfield browse definitions

The browse definition of every browse code, and the list of browse fields, as Twinfield returns them. Use them to design a browse query without asking Twinfield first: which fields a browse code has, which of them can be a selection criterion (`ask`), with which operator, and which values a field with a fixed list accepts (`browsefields.xml`).

```php
$result = $twinfield->browse('030_3')
    ->columns('fin.trs.head.code', 'fin.trs.head.number', 'fin.trs.line.valuesigned')
    ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
    ->equal('fin.trs.head.status', 'normal')
    ->get();
```

See [Browse data](https://php-twinfield.solvebeam.com/guides/browse-data/) in the documentation.

## A snapshot, not a specification

The files are read from one company on 9 November 2026 with `<read><type>browse</type>` and `<list><type>browsefields</type>`. Keep in mind:

- **Labels and names are in the language of the user** who read them, here Dutch (`Grootboekkaart`, `Periode`). The field names (`fin.trs.head.yearperiod`) and values (`normal`, `final`) are the same in every language.
- **Company data is replaced**: the company is `1000` "Example company", and the options of `fin.trs.head.bankcode` (the bank and cash day books of the company) are left out.
- **Twinfield does not apply the proposed criteria** (`from`, `to`, e.g. status `normal` or `$DEFAULT$` for the current period) to a query: send every criterion you need.
- A column with `<visible>false</visible>` is meant as criterion only. Asking for it as column can give a server error (e.g. `fin.trs.head.yearperiod` in 030_3).
- The definitions use the operators `none`, `equal`, `between` and `like` (not documented, in 130 and 230). `notbetween` also works.

The [Twinfield documentation](https://developers.twinfield.com/documentation/api/request-data/browse-data/) is leading. The [BrowseCodeDetails](https://developers.twinfield.com/documentation/assets/files/BrowseCodeDetails-c5a8066a60f8c2e2c0cb3cf6716641ce.pdf) overview of Twinfield shows the most used fields per browse code.

## Browse codes

| Code | Description (documentation) | Name (Dutch user) | Columns | Criteria |
| --- | --- | --- | ---: | ---: |
| [000](000.xml) | General ledger transactions | Grootboekkaart | 13 | 7 |
| [010](010.xml) | Transactions still to be matched | Nog af te letteren boekingen | 13 | 6 |
| [020](020.xml) | Transaction list | Invoerverslag | 21 | 15 |
| [100](100.xml) | Customer transactions | Debiteurenkaart | 21 | 10 |
| [200](200.xml) | Supplier transactions | Crediteurenkaart | 21 | 10 |
| [300](300.xml) | Project transactions | Projectkaart | 17 | 8 |
| [301](301.xml) | Asset transactions | Activakaart | 13 | 6 |
| [400](400.xml) | Cash transactions | Kasstaat | 17 | 7 |
| [410](410.xml) | Bank transactions | Bankkaart | 16 | 7 |
| [900](900.xml) | Cost centers | Kostenplaatsen | 14 | 6 |
| [030_1](030_1.xml) | General Ledger (details) | Grootboek (details) | 44 | 7 |
| [030_2](030_2.xml) | General Ledger (details) (v2) | Grootboek (details) (v2) | 46 | 8 |
| [030_3](030_3.xml) | General Ledger (details) (v3) | Grootboek (details) (v3) | 51 | 9 |
| [031](031.xml) | General Ledger (intercompany) | Grootboek (rekening-courant) | 42 | 6 |
| [031_2](031_2.xml) | General Ledger (intercompany)(v2) | Grootboek (rekening-courant) (v2) | 47 | 7 |
| [040_1](040_1.xml) | Annual Report (totals) | Annual Report | 31 | 7 |
| [050_1](050_1.xml) | Annual Report (YTD) | Annual Report YTD | 11 | 4 |
| [060](060.xml) | Annual Report (totals multicurrency) | Annual Report MC | 26 | 5 |
| [130_1](130_1.xml) | Customers | Debiteuren | 33 | 6 |
| [130_2](130_2.xml) | Customers (v2) | Debiteuren (v2) | 34 | 9 |
| [130_3](130_3.xml) | Customers (v3) | Debiteuren (v3) | 37 | 11 |
| [164](164.xml) | Credit Management | Kredietbeheer | 19 | 3 |
| [230_1](230_1.xml) | Suppliers | Crediteuren | 30 | 6 |
| [230_2](230_2.xml) | Suppliers (v2) | Crediteuren (v2) | 34 | 10 |
| [302_1](302_1.xml) | Fixed Assets | Activa | 24 | 4 |
| [610_1](610_1.xml) | Time & Expenses (Totals) | Uren en onkosten (Totalen) | 16 | 4 |
| [620](620.xml) | Time & Expenses (Multicurrency) | Uren en onkosten (MC) | 24 | 5 |
| [650_1](650_1.xml) | Time & Expenses (Details) | Uren en onkosten (Details) | 40 | 12 |
| [651_1](651_1.xml) | Time & Expenses (Totals per week) | Uren en onkosten (Totalen per week) | 37 | 11 |
| [652_1](652_1.xml) | Time & Expenses (Totals per period) | Uren en onkosten (Totalen per periode) | 37 | 11 |
| [660_1](660_1.xml) | Time & Expenses (Billing details) | Uren en onkosten (Facturatiedetails) | 34 | 11 |
| [661_1](661_1.xml) | Time & Expenses (Billing per week) | Uren en onkosten (Facturatie per week) | 27 | 10 |
| [662_1](662_1.xml) | Time & Expenses (Billing per period) | Uren en onkosten (Facturatie per periode) | 27 | 10 |
| [670](670.xml) | Transaction summary | Transactieoverzicht | 7 | 2 |
| [680](680.xml) | Bank link details | Bankkoppeling details | 7 | 1 |
| [680_2](680_2.xml) | Bank link details (v2) | Bankkoppeling details (v2) | 12 | 1 |
| [690](690.xml) | Vat Return status | Btw-aangifte status | 9 | 2 |
| [700](700.xml) | Hierarchy access | Rapportagestructuurtoegang | 10 | 1 |

[`browsefields.xml`](browsefields.xml) has all 262 browse fields with their data type (`string`, `special`, `value`, `long`, `decimal`, `date`, `datetime`), the Finder type to look up values (`finder`, e.g. `TRS` for day books) and, for `special` fields, the values to use in a criterion.

## Updating

```sh
composer twinfield:browse-definitions                     # default company
composer twinfield:browse-definitions -- --company=1000   # another company
```

The requests only read. The script logs each browse code (`saved`, `unchanged` or `failed`) and waits when Twinfield's rate limit is reached. Review the changes with `git diff browse-definitions` before committing: check that no company data is left. `tests/Unit/BrowseData/BrowseDefinitionFilesTest.php` reads every file with the SDK, and fails when a browse code has no file.
