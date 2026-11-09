---
title: Rate limits and concurrency limits
description: "Twinfield knowledge base: how many requests per minute and how many concurrent requests the Twinfield API allows, and how to handle a 429 response with this SDK."
---

Since 1 October 2024, Twinfield limits how many requests an integration may send per minute, and how many it may send at the same time. Over a limit, Twinfield answers HTTP 429 Too Many Requests and does not process the request. This article from the Twinfield community gives the limits.

:::caution[Newer limits on the developer portal]
The [Fair Use Policy](https://developers.twinfield.com/documentation/getting-started/fair-use-policy#rate-limits) on the Twinfield developer portal is newer and differs from this article: it counts API credits instead of requests, counts more requests as queries, and has other response headers. The [Rate limits](/guides/rate-limits/) guide follows the developer portal.
:::

## The article

**Source:** [Rate limiting and concurrency limits](https://wktaaeu.my.site.com/nlcommunity/s/detail/ka007000000gTxdAAE), Twinfield community (Wolters Kluwer). Quoted on 9 November 2026.

> **Rate limiting and concurrency limits**
>
> Our API will start to enforce API limits to ensure fair usage of resources by 1st October 2024 and will affect all users of our Webservices and REST APIs. Adhering to the new API limits will ensure applications will continue to function optimally and that they will not be affected by potential downtime, errors, or slow responses.
>
> If the rate limit or concurrency limit is exceeded, you will receive a 429 Too Many Requests response and the request will not be further processed.
>
> **API Client guidelines**
>
> - Implement proper error handling in your application to handle rate limit and concurrency control responses
> - Limit the amount of concurrent requests from your API client to stay within the limits
> - Use exponential backoff for retrying requests when rate limits are exceeded. Use as a start backoff time the "Retry-After" header value.
> - Monitor your API usage and the response codes regularly to stay within the rate limits and concurrency limits.
>
> Thank you for using our API! If you have any question or need assistance, please contact our support team via our support portal or, if you have access, via our developer portal.
>
> **Rate limits**
>
> Our API enforces different rate limits for fast 'Query' requests and 'other' requests.
>
> **Query requests**
>
> 'Query' requests are defined as:
>
> - HTTP GET Method calls
> - HTTP OPTIONS Method calls
> - Calls to the following endpoints:
>   - /webservices/finder.asmx
>   - /webservices/*.svc where the SOAP action starts with ‘Get’ (header SOAPAction)
>   - /webservices/*.asmx where the SOAP action starts with ‘Get’ (header SOAPAction)
> - ProcessXML requests with the following root elements:
>   - List
>   - Read
>
> 'Query' requests that return a response within 1 second are subject to the following rate limits:
>
> | | |
> |---|---|
> | Per IP Address | 1000 requests per minute |
> | Per ClientId | 1000 requests per minute |
> | Per OrganisationId | 1000 requests per minute |
> | Per ClientId and OrganisationId combination | 500 requests per minute |
>
> **Other requests**
>
> All other requests, including 'Query' requests that take more than 1 second, are subject to the following rate limits:
>
> | | |
> |---|---|
> | Per IP Address | 300 requests per minute |
> | Per ClientId | 300 requests per minute |
> | Per OrganisationId | 300 requests per minute |
> | Per ClientId and OrganisationId combination | 120 requests per minute |
>
> Please ensure that your application does not exceed these rate limits to avoid a service disruption. If you need help to adhere to the new rate limits please contact our support via the support portal.
> _Please be aware we do not support higher API limits._
>
> These limits are maximums and are not guaranteed: see our [Fair Use Policy](https://accounting.twinfield.com/webservices/documentation/#/GettingStarted/FUP) for more information.
>
> **Response headers**
>
> The API response will include the following rate limit headers in each request:
>
> | | |
> |---|---|
> | **X-RateLimit-Limit** | Request limit per minute |
> | **X-RateLimit-Remaining** | The number of remaining requests available within the current time window. Due to the distributed nature of our infrastructure the rate limiting information may not be completely accurate. |
>
> In case the request is rejected due to rate limiting, the following header will also be present:
>
> | | |
> |---|---|
> | **Retry-After** | The recommended retry interval in seconds after the specified call rate is exceeded. |
>
> **Concurrency Control**
>
> To prevent abuse and maintain optimal service performance, we enforce concurrency control. Each ClientId can have a maximum of 20 concurrent requests. Each ClientId can have a maximum of 10 concurrent requests per organisation.
> For example: a single ClientId can have 5 concurrent requests for 4 different organisations at the time.
> All requests are subject to the following concurrency limits:
>
> | | |
> |---|---|
> | Per ClientId | 20 concurrent requests |
> | Per ClientId and OrganisationId combination | 10 concurrent requests |
>
> If the concurrency limit is exceeded, you will receive a 429 Too Many Requests response.
>
> **Response headers**
>
> The API response will include the following concurrency limit headers in each request:
>
> | | |
> |---|---|
> | **X-Concurrency-Limit** | Concurrent requests allowed at any time |
>
> **Related articles:**
>
> - [How to adjust my Twinfield API connection to match the API limit on number of transaction lines?](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-How-to-adjust-my-Twinfield-API-connection-to-match-the-API-limit-on-number-of-transaction-lines)
> - [How to adjust my API connection to fit Twinfield API limits?](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-practices-How-to-adjust-my-API-connection-to-fit-Twinfield-API-limits)
> - [API Best Practices - Introduction](https://wktaaeu.my.site.com/nlcommunity/s/article/API-Best-Practices-Introduction)

The Fair Use Policy link in the article goes to the old API reference, and the related articles are relative links on the community site, written out in full here. The third related article is quoted on [API best practices](/knowledge-base/api-best-practices/). The current page is on the Twinfield developer portal: [Fair use policy](https://developers.twinfield.com/documentation/getting-started/fair-use-policy#rate-limits).

## With this SDK

The [Rate limits](/guides/rate-limits/) guide has the current limits from the developer portal, which query requests the SDK sends, and how to handle a 429. It also lists [where this article differs](/guides/rate-limits/#the-community-article) from the developer portal.

On HTTP 429 the SDK throws a `RateLimitExceededException`. `concurrencyLimitExceeded` says which limit was exceeded, and `retryAfterSeconds` has the `Retry-After` header:

```php
use SolveBeam\Twinfield\Soap\RateLimitExceededException;

try {
    $customer = $twinfield->customers()->get('1000');
} catch (RateLimitExceededException $e) {
    sleep($e->retryAfterSeconds ?? 1);

    $customer = $twinfield->customers()->get('1000');
}
```

For exponential backoff and how to stay under the limits, see [With this SDK](/guides/rate-limits/#with-this-sdk) in the guide. See also [Error handling](/examples/getting-started/error-handling/).
