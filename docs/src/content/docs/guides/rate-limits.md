---
title: Rate limits
description: How many API credits per minute and how many concurrent requests Twinfield allows, which requests count as queries, and how to handle a 429 response with this SDK.
---

Twinfield limits how many requests an integration may send per minute (the rate limit) and how many it may send at the same time (the concurrency limit). Over a limit, Twinfield answers HTTP 429 Too Many Requests and does not process the request. This page follows [Fair Use Policy and Limitations](https://developers.twinfield.com/documentation/getting-started/fair-use-policy#rate-limits) on the Twinfield developer portal, read on 9 November 2026. The developer portal is leading: the limits can change.

## Rate limit: API credits

Every request costs API credits: a query request costs 1 credit, any other request 3 credits. Twinfield counts the credits in four buckets, and the first bucket that runs out stops your requests:

| Bucket | API credits per minute |
|---|---|
| Per IP address | 1000 |
| Per client ID | 1000 |
| Per organisation | 1000 |
| Per client ID and organisation | 500 |

The limits per client ID are for [certified integrations](https://wktaaeu.my.site.com/nlcommunity/s/article/Wat-komt-er-aan-bod-tijdens-de-certificering?language=en_US). A new client ID that is not certified yet gets 5% of them: 50 credits per minute, and 25 per organisation. Twinfield does not raise the limits on request.

### Query requests

Twinfield counts these requests as query requests (1 credit):

| Twinfield | In this SDK |
|---|---|
| HTTP `GET` and `OPTIONS` | none: the SDK sends every API request as `POST` |
| `/webservices/*.asmx` with a SOAP action that starts with `Get`, `Query`, `Search` or `Load` | the Finder (`Search`): searching master data, offices, users, … |
| `/webservices/*.svc` with a SOAP action that starts with `Get`, `Query`, `Search` or `Load` | the queries of the Command & Query services, e.g. periods (`Query`) |
| `/webservices/DeletedTransactionsService.svc` | [Deleted transactions](/guides/transactions/#deleted-transactions) |
| ProcessXML with the root element `list`, `read`, `columns`, `accountcode` or `accountcodes` | every `get()` and `list()` on the [XML web services](/guides/process-xml/) |

Creating, updating, posting and deleting are other requests (3 credits). So a read costs a third of a write: 1000 reads per minute fit in a bucket, but only 333 writes.

## Concurrency limit

| | Concurrent requests |
|---|---|
| Per client ID | 20 |
| Per client ID and organisation | 10 |

One client ID can, for example, send 5 requests at the same time for each of 4 organisations. Over the limit, the 429 response says `Concurrency limit exceeded.`

## Response headers

Twinfield sends these headers with every response, not only with a 429:

| Header | Meaning |
|---|---|
| `X-RateLimit-Limit` | API credits per minute: the lowest limit of the four buckets |
| `X-RateLimit-Remaining` | API credits left in the current minute: the lowest of the four buckets |
| `X-RateLimit-ClientId-Limit` | API credits per minute for the client ID |
| `X-RateLimit-ClientId-Remaining` | API credits left in the current minute for the client ID. When `X-RateLimit-Remaining` is 0 but this is not, requests for other organisations still work. |
| `X-RateLimit-Credited` | API credits this request cost: shows whether it counted as a query request |

A 429 for the rate limit also has `Retry-After`: the recommended wait in seconds. Twinfield sends no headers for the concurrency limit. The rate limit numbers may not be completely accurate, Twinfield warns, because of its distributed infrastructure.

## With this SDK

On HTTP 429 the SDK throws a `RateLimitExceededException`. It does not retry by itself, so your integration decides how long to wait.

| Property | Value |
|---|---|
| `concurrencyLimitExceeded` | `true` when the response says `Concurrency limit exceeded.`, otherwise `false` (the rate limit) |
| `retryAfterSeconds` | `Retry-After` |
| `rateLimitLimit` | `X-RateLimit-Limit` |
| `rateLimitRemaining` | `X-RateLimit-Remaining` |
| `rateLimitClientIdLimit` | `X-RateLimit-ClientId-Limit` |
| `rateLimitClientIdRemaining` | `X-RateLimit-ClientId-Remaining` |
| `rateLimitCredited` | `X-RateLimit-Credited` |

The header properties are integers, or `null` when Twinfield did not send the header. The SDK only reads the headers of a 429; it does not return them for a successful request.

Twinfield recommends exponential backoff that starts at the `Retry-After` value:

```php
use SolveBeam\Twinfield\Soap\RateLimitExceededException;

/**
 * @template T
 * @param callable(): T $request
 * @return T
 */
function withBackoff(callable $request, int $attempts = 5): mixed
{
    for ($attempt = 1; ; $attempt++) {
        try {
            return $request();
        } catch (RateLimitExceededException $e) {
            if ($attempt === $attempts) {
                throw $e;
            }

            // Start at Retry-After and double the wait on every next attempt.
            // A concurrency limit has no Retry-After: a short wait is enough.
            sleep(($e->retryAfterSeconds ?? 1) * 2 ** ($attempt - 1));
        }
    }
}

$customer = withBackoff(fn () => $twinfield->customers()->get('1000'));
```

To stay under the limits:

- Save many transactions in one request with `saveMany()` instead of one request per transaction. It is on every transaction type: `salesTransactions()`, `purchaseTransactions()`, `cashTransactions()`, `journalTransactions()` and `bankTransactions()`. See [Saving many transactions](/guides/transactions/#saving-many-transactions). For electronic bank statements, `createMany()` does the same: see [Electronic bank statements](/guides/transactions/#electronic-bank-statements).
- Keep a shadow database instead of reading the same data again and again: see [Keys for a shadow database](/guides/organisations/#keys-for-a-shadow-database).
- Find changed master data of all companies at once: `search()->inAllCompanies()->modifiedSince($since)` is one request for the whole organisation instead of one per company. See [Changes in a whole organisation](/guides/finder/#changes-in-a-whole-organisation).
- Send requests one after another per organisation, or limit your workers to at most 10 at the same time per organisation.

See also [Error handling](/examples/getting-started/error-handling/) and, in the knowledge base, [API best practices](/knowledge-base/api-best-practices/).

## Introduction of the limits

Until 1 December 2024 Twinfield did not limit the number of requests of an integration or the number of entities it read. Wolters Kluwer announced the limits by email to integrators on 23 September 2024 ("API-limieten ingaande per 1 december 2024"):

- The reason: strong growth of the API use, mostly creating transactions. Some entities use many resources on the Twinfield servers: transaction lines, invoice lines and dimensions. Web services count as API.
- From 1 December 2024 Twinfield enforces a rate limit and a concurrency limit, with a `429 Too Many Requests` response over the limit. Integrations should respect the limits and not cause these responses often.
- The number of requests per minute is limited to 1000. The email gave each integration its measured peak per minute, so it could check whether it had to change.

The same email announced the end of:

| Feature | Date |
|---|---|
| The root element `general` in ProcessXML and ProcessXMLDocument | 1 September 2024 |
| The `template` field of users | 31 December 2024 |
| `ProcessXmlCompressed` | 31 December 2024 |

The SDK uses none of them: it sends ProcessXML uncompressed with `ProcessXmlString`, and `OfficeGeneral` is the `general` element inside an `<office>` response, not the root element.

## The community article

The Twinfield community has an older article about the same limits: [Rate limits and concurrency limits](/knowledge-base/rate-limits-and-concurrency-limits/). It differs from the developer portal:

- It counts requests instead of API credits: 1000 query requests or 300 other requests per minute (500 and 120 per client ID and organisation), and a query request that takes more than 1 second counts as an other request.
- Only SOAP actions that start with `Get`, the Finder, and ProcessXML `list` and `read` are query requests.
- It has no `X-RateLimit-ClientId-…` and `X-RateLimit-Credited` headers, but an `X-Concurrency-Limit` header that, according to the developer portal, Twinfield does not send.
- It does not mention the 5% for client IDs that are not certified.
- The limits start on 1 October 2024 instead of 1 December 2024.
