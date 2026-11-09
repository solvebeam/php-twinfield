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

## Twinfield knowledge base

- Articles from the Twinfield community go in `docs/src/content/docs/knowledge-base/`: the article text quoted unchanged in a blockquote, with the source link and dates, followed by a "With this SDK" section with code and links to examples. See the `index.md` there.

## Twinfield terminology

- Twinfield is moving from the term "office" to "company" (administration). In the Finder the `company` option is preferred and `office` is deprecated but still supported; don't pass both. See https://developers.twinfield.com/documentation/api/miscellaneous/finder/.
- Use "company" for new code and options. The existing `Office` classes keep their name for now, but keep the rename in mind.
