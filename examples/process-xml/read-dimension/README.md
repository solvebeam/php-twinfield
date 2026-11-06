# Read dimension

Reads a customer (dimension type `DEB`) with ProcessXml and shows what to do when Twinfield does not accept the request.

Build requests with DOM, as Twinfield recommends, so values such as `&` are escaped. A string works too: `processXml('<read>…</read>')`. Before sending, comments and whitespace between elements are removed, as Twinfield asks.

Twinfield marks the result of each element with a `result` attribute: `1` processed, `0` not. When the root element has `result="0"`, `processXml()` throws a `ProcessXmlException`. Its `result` has the whole response, and `messages()` gives each error and warning (`msg` and `msgtype`) with the path of the element, e.g. `dimension/code`. In a bulk request (e.g. `<transactions>` with several transactions) the other elements may have been processed: check their `result` attribute.

Warnings on a processed request are in `$result->warnings()`.

## Code

```php
$twinfield = Example::twinfield();

$code = $argv[1] ?? '1000';

// Build the request with DOM, so values are escaped.
$request = new DOMDocument();
$read = $request->appendChild($request->createElement('read'));

$fields = [
    'type' => 'dimensions',
    'office' => (string) $twinfield->companyCode(),
    'dimtype' => 'DEB',
    'code' => $code,
];

foreach ($fields as $name => $value) {
    $read->appendChild($request->createElement($name))->appendChild($request->createTextNode($value));
}

try {
    $result = $twinfield->processXml($request);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

$xpath = $result->xpath();

echo 'Code:    ', $xpath->evaluate('string(/dimension/code)'), PHP_EOL;
echo 'Name:    ', $xpath->evaluate('string(/dimension/name)'), PHP_EOL;
echo 'Type:    ', $xpath->evaluate('string(/dimension/type)'), PHP_EOL;
echo 'Group:   ', $xpath->evaluate('string(/dimension/type/@name)'), PHP_EOL;
echo 'Status:  ', $xpath->evaluate('string(/dimension/@status)'), PHP_EOL;
echo 'Website: ', $xpath->evaluate('string(/dimension/website)'), PHP_EOL;
```

## Run

```sh
php examples/process-xml/read-dimension/example.php 1000
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
Code:    1000
Name:    Example Customer B.V.
Type:    DEB
Group:   Debiteuren
Status:  active
Website: www.example.com
```

With a code that does not exist:

```text
error at dimension/code: De code voldoet niet aan het formaat 1[0-9][0-9][0-9].
```
