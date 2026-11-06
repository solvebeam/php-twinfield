---
title: Development and testing
description: Set up a local environment, run the tests and record fixtures from real responses.
---

## One-time setup

Register `http://localhost:8765/callback` as redirect URI for your client in the Twinfield developer portal, then run:

```sh
composer twinfield:setup
```

This asks for your client ID, client secret and redirect URI, opens the Twinfield login in your browser and asks for a default company. Running any example for the first time does the same. Values are saved outside the project, in `~/.config/twinfield/` (only readable by you):

```dotenv
# ~/.config/twinfield/.env
TWINFIELD_CLIENT_ID=…
TWINFIELD_CLIENT_SECRET=…
TWINFIELD_REDIRECT_URI=http://localhost:8765/callback
TWINFIELD_COMPANY=1002
TWINFIELD_WRITE_COMPANIES=1002,1002_3   # optional, for write tests
```

The refresh token is stored in `~/.config/twinfield/token.json`; access tokens are refreshed automatically.

An optional `.env` in the project root (gitignored) overrides values, and real environment variables override both. Set `TWINFIELD_CONFIG_DIR` to use another folder.

## Commands

| Command | What it does |
|---|---|
| `composer test` | Offline unit tests on fixtures. Fast, no network. |
| `composer test:live` | Read-only tests against the live API (`tests/Integration`), including running every example. Skipped without login. |
| `composer test:write` | Write tests (`tests/Write`). Asks you to type the company code first; only companies in `TWINFIELD_WRITE_COMPANIES`. |
| `composer twinfield -- finder DIM "*" 10` | Try a live call and see the SOAP request and response. Also `whoami`, `refresh`, `--company=CODE`, `--quiet`. |
| `composer twinfield:setup` | Guided setup: credentials, login and default company. Run again to change them. |
| `composer twinfield:login` | Log in (again), e.g. when the refresh token has expired. Use `-- --manual` to paste the redirected URL. |
| `composer twinfield:anonymize` | Turn recorded responses into anonymized fixtures. |

## Environment switches

- `TWINFIELD_DEBUG=1`: print every SOAP request and response (access token masked).
- `TWINFIELD_RECORD=1`: save raw responses to `tests/Fixtures/raw/` (gitignored, contains real data).
- `TWINFIELD_COMPANY=1002_3`: run against another administration.

## Fixtures from real responses

```sh
TWINFIELD_RECORD=1 composer test:live
composer twinfield:anonymize
git diff tests/Fixtures/soap
```

The anonymizer keeps the XML structure and replaces every value with a fake value in the same format (digits stay digits, dates stay valid dates). It uses a secret key in `~/.config/twinfield/anonymizer.key`, so the same input always gives the same output. Check the list of kept values it prints before committing.

## Safety

All live calls go through a write guard. Requests that may change data (anything that is not a search, get, query or ProcessXml `read`/`list`/browse) are blocked unless writes were confirmed for that company via `composer test:write`.

