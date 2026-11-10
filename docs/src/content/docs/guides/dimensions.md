---
title: Dimensions
description: How Twinfield registers transactions on general ledger accounts and analyses them on three dimension levels, with relations, cost centers, projects and assets.
---

As in most accounting packages, in Twinfield you register financial transactions on general ledger accounts and analyse them further, for example on accounts receivable (customers), accounts payable (suppliers), cost centers, projects or assets. Twinfield calls these **dimensions**. The level of a dimension determines what you can register:

- **Level 1**: the balance sheet and profit and loss accounts.
- **Level 2**: relations (customers and suppliers) and cost centers.
- **Level 3**: projects and assets.

Source: [Dimensions](https://developers.twinfield.com/documentation/getting-started/dimensions) in the Twinfield API documentation.

```mermaid
flowchart LR
    subgraph level1["Dimension 1"]
        direction TB
        subgraph BAS["Balance (BAS)"]
            direction TB
            bas0110["0110 Buildings"] ~~~ bas1010["1010 Bank"] ~~~ bas1300["1300 Debtors"] ~~~ bas1600["1600 Creditors"]
        end
        subgraph PNL["Profit & Loss (PNL)"]
            direction TB
            pnl4000["4000 Salary"] ~~~ pnl4100["4100 Rent"]
        end
        BAS ~~~ PNL
    end

    subgraph level2["Dimension 2"]
        direction TB
        subgraph DEB["Debtors (DEB)"]
            direction TB
            deb1000["1000 Walter BV"] ~~~ deb1001["1001 Stars"]
        end
        subgraph CRD["Creditors (CRD)"]
            direction TB
            crd2000["2000 KPN"] ~~~ crd2001["2001 Floors & Units"]
        end
        subgraph KPL["Cost Centres (KPL)"]
            direction TB
            kpl00001["00001 General"] ~~~ kpl00002["00002 Management"]
        end
        DEB ~~~ CRD ~~~ KPL
    end

    subgraph level3["Dimension 3"]
        direction TB
        subgraph PRJ["Projects (PRJ)"]
            direction TB
            prjP0001["P0001 Project 1"] ~~~ prjP0002["P0002 Project 2"]
        end
        subgraph AST["Assets (AST)"]
            direction TB
            ast60001["60001 Building"] ~~~ ast60002["60002 Truck"]
        end
        PRJ ~~~ AST
    end

    level1 ~~~ level2 ~~~ level3
```

## With this SDK

Each group in the diagram is a dimension type. The SDK has a resource for each of them, see [Master data (dimensions)](/guides/master-data/):

| Level | Dimension type | Resource |
|---|---|---|
| 1 | Balance sheet (`BAS`) | [`$twinfield->balanceSheetAccounts()`](/guides/master-data/#general-ledger-accounts) |
| 1 | Profit and loss (`PNL`) | [`$twinfield->profitAndLossAccounts()`](/guides/master-data/#general-ledger-accounts) |
| 2 | Customers (`DEB`) | [`$twinfield->customers()`](/guides/master-data/#customers) |
| 2 | Suppliers (`CRD`) | [`$twinfield->suppliers()`](/guides/master-data/#suppliers) |
| 2 | Cost centers (`KPL`) | [`$twinfield->costCenters()`](/guides/master-data/#cost-centers) |
| 3 | Projects (`PRJ`) | [`$twinfield->projects()`](/guides/master-data/#projects) |
| 3 | Fixed assets (`AST`) | [`$twinfield->classicFixedAssets()`](/guides/master-data/#fixed-assets-classic) |

Use [dimension types](/guides/master-data/#dimension-types) to read the settings of a dimension type, such as its name and code mask, and [dimension groups](/guides/master-data/#dimension-groups) for named sets of dimensions, e.g. your key account customers.
