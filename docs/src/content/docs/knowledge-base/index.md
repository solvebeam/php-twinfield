---
title: Twinfield knowledge base
description: Articles from the Twinfield community, quoted with their source, and how to do the same with this SDK.
sidebar:
  order: 0
  label: About
---

Twinfield (Wolters Kluwer) answers many API questions in articles on the [Twinfield community](https://wktaaeu.my.site.com/nlcommunity/s/). This section keeps the articles that matter for developers, so they are easy to find next to the SDK documentation.

Each page has two parts:

1. **The article**: quoted as it was published, in its original language, with a link to the source and the dates. The text is by Twinfield, not by this project. The source is leading: articles can change after we quoted them.
2. **With this SDK**: how to do the same with this SDK, with links to runnable [examples](/examples/).

## Articles

- [API best practices](/knowledge-base/api-best-practices/): _API Best Practices - Introduction_, the overview of Twinfield's series
- [Rate limits and concurrency limits](/knowledge-base/rate-limits-and-concurrency-limits/): _Rate limiting and concurrency limits_
- [Sales invoice or sales transaction?](/knowledge-base/sales-invoice-or-sales-transaction/): _Hoe maak ik via webservices een verkoopfactuur of een verkoopboeking aan?_

## Adding an article

Add a page in `docs/src/content/docs/knowledge-base/` with:

- an English `title` that says what the article answers, and the original title in the quote;
- a "Source" line with the link, the created and updated dates of the article and the date it was quoted;
- the article text in a blockquote, unchanged apart from Markdown formatting (lists, links);
- a "With this SDK" section with code and links to examples.

Then add it to the list above.
