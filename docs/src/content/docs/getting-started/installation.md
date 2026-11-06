---
title: Installation
description: Install the SDK with Composer and pick a PSR-18 HTTP client.
---

## Requirements

- PHP 8.3 or newer
- A [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and [PSR-17](https://www.php-fig.org/psr/psr-17/) factories
- A Twinfield client ID and secret from the [Twinfield developer portal](https://developers.twinfield.com)

## Install

```sh
composer require solvebeam/twinfield
```

The SDK does not ship an HTTP client. Install one you like, for example Symfony's lightweight client with Nyholm's PSR-7 implementation:

```sh
composer require symfony/http-client nyholm/psr7
```

Or a pure cURL client:

```sh
composer require php-http/curl-client nyholm/psr7
```

`Twinfield::create()` finds an installed client and factories automatically through [php-http/discovery](https://docs.php-http.org/en/latest/discovery.html). You can also pass your own, see [Authentication](/examples/getting-started/authentication/).

## Optional packages

| Package | For |
|---|---|
| `symfony/cache` | A PSR-6 cache for access tokens |
| `monolog/monolog` | PSR-3 logging of HTTP requests and responses |
| `php-http/logger-plugin` | Logging HTTP traffic (needs a PSR-3 logger) |
| `php-http/message` | Message formatters, such as `FullHttpMessageFormatter` for complete request and response logging |

## Next

1. [Let a user log in](/examples/getting-started/login/) and get a refresh token.
2. [Create a client](/examples/getting-started/authentication/) in your own application.
3. [Run the examples](/examples/) to see it work against your own company.
