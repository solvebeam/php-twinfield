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
- Money amounts are always numeric strings (`'121.00'`), never floats and never integer cents. Use the `Amount` value object (bcmath, scale 2) for amounts in models and inputs; accept `Amount|string` with `@param numeric-string`.
- Keep read models (what Twinfield returns) separate from objects for creating or updating data. Fields that are optional when creating something must not make the read model nullable.

## Twinfield terminology

- Twinfield is moving from the term "office" to "company" (administration). In the Finder the `company` option is preferred and `office` is deprecated but still supported; don't pass both. See https://developers.twinfield.com/documentation/api/miscellaneous/finder/.
- Use "company" for new code and options. The existing `Office` classes keep their name for now, but keep the rename in mind.
