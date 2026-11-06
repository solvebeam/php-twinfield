---
title: PSR standards
description: Which PHP-FIG standards the SDK follows and uses.
---

## PSR-1 (Basic Coding Standard)

The codebase follows PSR-1 fundamentals for PHP files and class structure.

## PSR-2 (Coding Style Guide)

PSR-2 is superseded by PSR-12; this project follows PSR-12.

## PSR-4 (Autoloading)

The SDK uses PSR-4 autoloading for the `SolveBeam\Twinfield\` namespace, configured in `composer.json`.

## PSR-6 (Caching)

Examples use PSR-6 caching for token storage via `symfony/cache`.

- https://www.php-fig.org/psr/psr-6/
- https://github.com/php-fig/cache
- https://packagist.org/packages/psr/cache
- https://github.com/symfony/cache

## PSR-7 (HTTP Messages)

The OAuth2 provider relies on PSR-7 request/response interfaces for HTTP messages.

## PSR-18 (HTTP Client)

The library can work with PSR-18 HTTP clients. Suggested implementations are listed in `composer.json` (e.g., `symfony/http-client`, `php-http/curl-client`) along with a PSR-7 implementation such as `nyholm/psr7`.

## PSR-12 (Coding Style)

Coding style follows PSR-12, enforced via the PHPCS ruleset in `phpcs.xml.dist`.

