---
title: XML web services (ProcessXml)
description: Send XML requests to Twinfield with processXml(), read the response and handle errors and warnings.
---

The [XML web services](https://developers.twinfield.com/documentation/api/types/xml-web-services/) take one XML document per request, e.g. `<read>`, `<list>`, `<dimension>` or `<transactions>`. The SDK sends it with `ProcessXmlString` on `processxml.asmx` and gives the response back as a DOM document.

## Sending a request

```php
$result = $twinfield->processXml('<read><type>dimensions</type><office>1000</office><dimtype>DEB</dimtype><code>1000</code></read>');

echo $result->xpath()->evaluate('string(/dimension/name)'), PHP_EOL;
```

`processXml()` takes a string or a `DOMDocument`. Twinfield recommends building requests with DOM, so values such as `&` are escaped. Before sending, the SDK:

- removes comments and whitespace between elements, which Twinfield does not accept;
- sends the XML as UTF-8;
- refuses invalid XML and the deprecated `<general>` root element with an `InvalidArgumentException`.

The client's company goes in the header. Listing companies is the one request that works without company:

```php
$result = $twinfield->withoutCompany()->processXml('<list><type>offices</type></list>');
```

See [List companies](/examples/process-xml/list-companies/) and [Read dimension](/examples/process-xml/read-dimension/).

## Reading the response

`ProcessXmlResult` gives the response as `document()`, `root()` and `xpath()`. Twinfield adds these attributes to the elements:

| Attribute | Values | Meaning |
|---|---|---|
| `result` | `1`, `0` | Element processed (`1`) or not (`0`). |
| `msgtype` | `error`, `warning` | Type of the message in `msg`. |
| `msg` | text | Error or warning. |
| `name` | text | Name of the entity whose code is in the element. |
| `shortname` | text | Short name of that entity. |
| `type` | code | Type of the dimension in the element. |

For example, `<currency>EUR</currency>` comes back as `<currency name="Euro" shortname="€">EUR</currency>`.

## Errors and warnings

When the root element has `result="0"`, `processXml()` throws a `ProcessXmlException`. Its message has the first error and the path of its element; `$exception->result` has the whole response:

```php
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;

try {
    $twinfield->processXml($request);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }
}
```

Each `ProcessXmlMessage` has a `type` (`MessageType::Error` or `MessageType::Warning`), the `text`, a `path` such as `transactions/transaction[2]/header/period` and the DOM `element`. `errors()` and `warnings()` filter them. A processed request can still have warnings: check `$result->warnings()`.

In a bulk request, e.g. `<transactions>` with several `<transaction>` elements, the root gets `result="0"` when one of them fails, but the others may have been processed. Check the `result` attribute of each element in `$exception->result`.

A response without a valid `result` attribute on the root element, or a message with an unknown `msgtype`, throws an `UnexpectedResponseException`. Errors in the request itself, such as an invalid access token, come as SOAP faults: see [Error handling](/examples/getting-started/error-handling/).

## Value types

Twinfield expects these formats in a request and uses them in the response:

| Type | Format | Example |
|---|---|---|
| boolean | `true` or `false` | `true` |
| date | `yyyymmdd` | `20250131` |
| money | dot as decimal separator | `12.50` |
| period | `yyyy/p` or `yyyy/pp` | `2025/01` |
| code | at most 16 characters: `A-Z`, `0-9`, `_`, `-`, `+` | `DEB` |

The value objects of the SDK give money and periods in this form: `(string) Amount::of('12.5')` is `12.50` and `(string) Period::of(2025, 1)` is `2025/01`. Format a date with `$date->format('Ymd')`. Pass amounts as string: floats are not exact. Other types (string with a maximum length, integer, fixed value, enumeration) need no conversion.

## Not supported

| Part of the API | Why |
|---|---|
| `ProcessXmlDocument` | Only for .NET, according to Twinfield; `ProcessXmlString` does the same. |
| `ProcessXmlCompressed` | [Deprecated](https://developers.twinfield.com/documentation/getting-started/breaking-changes#processxmlcompressed) and will be removed. |
| `<general>` root element | [Deprecated](https://developers.twinfield.com/documentation/getting-started/breaking-changes#general-root-xml-element): send one request per type, e.g. `<transactions>` or `<dimensions>`. |

The requests for the entities themselves (dimensions, transactions, …) are on the other pages of the [Twinfield API documentation](https://developers.twinfield.com/documentation/api/master-data/activities/). Most have a resource in the SDK, e.g. [browse data](/guides/browse-data/); send the others with `processXml()`.
