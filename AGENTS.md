# AGENTS.md

## Code style

- Pass multiple arguments to `echo` separated by commas instead of concatenating them with `.`:

  ```php
  echo $office->code, ' ', $office->name, PHP_EOL;
  ```

  Not:

  ```php
  echo $office->code . ' ' . $office->name . PHP_EOL;
  ```

## Type safety and errors

- Keep models strict: required values are non-nullable and validated at runtime (e.g. throw in the constructor when a required string is empty). Don't make properties nullable "just in case".
- Never hide unexpected API responses behind fallbacks such as `?? ''`, `?? 0` or `?? []`. Throw an exception instead: `UnexpectedResponseException` for responses that don't match what we expect, `InvalidArgumentException` for invalid input. Only use a fallback when the empty case is valid Twinfield behaviour (e.g. a list element that is left out when it has no items), and say so in a comment.
- Money is always a numeric string (`'121.00'`), never a float and never integer cents. Wrap it in a value object with bcmath that keeps the number of decimals Twinfield uses: `Amount` (2 decimals) for transaction amounts, `UnitPrice` (4 decimals) for rate prices. Accept `Amount|string` (or `UnitPrice|string`) with `@param numeric-string`.
- Keep read models (what Twinfield returns) separate from objects for creating or updating data. Fields that are optional when creating something must not make the read model nullable.

## Models and wire formats

Twinfield has SOAP/XML web services now and is moving towards a REST/JSON API (https://api.accounting.twinfield.com/Api/swagger/ui/index). Keep the models usable for both: the XML (or JSON) is how data travels, not what it is.

- Value objects (`Amount`, `UnitPrice`, `Period`, …) know their own rules: validation, parsing and their canonical string form (`2026/03`, `121.00`). They never depend on `ProcessXml`, `Soap` or `CommandQuery` classes. The XML layer uses the value object, not the other way round: `XmlValue::period()` calls `Period::of()`.
- Constructors and properties of models are format-neutral: plain PHP types, enums and value objects, no `XmlElement`, `DOMElement` or XML strings, and no names that only exist in the XML (`dim1`, `@location`) when a clear name exists.
- Mapping from and to a wire format stays at the edge. Many existing models have a static `fromXml()` / `fromResponse()`; that is accepted, but keep it a thin mapping onto the constructor. For new code, prefer mapping in the resource or a separate reader/writer class (like `TransactionXmlWriter`) over adding XML code to a model.
- Don't refactor existing models just for this; apply it to new code and to code you touch anyway.

## Documentation

- Write guides and example READMEs for developers who connect to their own organisation. Don't mention the numbers, dates or results of our own live tests (`557 customers changed since 1 October`, `491 VAT codes of 12 companies`, `18 against 31 users`): describe the behaviour instead. Such details belong in `TODO.md` and on the "Differences with the documentation" page, which record how something was verified.

## Twinfield knowledge base

- Articles from the Twinfield community go in `docs/src/content/docs/knowledge-base/`: the article text quoted unchanged in a blockquote, with the source link and dates, followed by a "With this SDK" section with code and links to examples. See the `index.md` there.

## Twinfield terminology

- Twinfield is moving from the term "office" to "company" (administration). In the Finder the `company` option is preferred and `office` is deprecated but still supported; don't pass both. See https://developers.twinfield.com/documentation/api/miscellaneous/finder/.
- Use "company" for new code and options. The existing `Office` classes keep their name for now, but keep the rename in mind.
- Name menu items, page titles and headings (guides, examples, `examples/README.md`, README, documentation differences, TODO.md) after the Twinfield API documentation, so readers recognise them: "Hierarchies", not "Reporting structures" (https://developers.twinfield.com/documentation/api/miscellaneous/hierarchy/). Use the term from the Twinfield interface (e.g. "reporting structure", rapportagestructuur) in the text to explain what it is.
- Spell "organisation" the British way, as Twinfield does in its scopes (`twf.organisation`), claims (`twf.organisationId`) and error codes (`OrganisationInactive`). Never use "organization" in class names, methods, properties or docs, even though it is the more common spelling among developers (e.g. GitHub). See `docs/src/content/docs/guides/organisations.md`.
