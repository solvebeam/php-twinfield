# List companies

Lists all companies the user has access to, with a request on the [XML web services](https://developers.twinfield.com/documentation/api/types/xml-web-services/) (ProcessXml).

`processXml()` sends an XML request to `processxml.asmx` and returns a `ProcessXmlResult`. Use `root()`, `document()` or `xpath()` to read the response with DOM, or `xml()` for the response as string, e.g. for SimpleXML or XMLReader.

ProcessXml requests normally work on one company, but listing companies is the exception: Twinfield asks to send it without a company in the header. `withoutCompany()` returns a client without company. Twinfield adds the `name` and `shortname` of each company to the response.

The [List offices](../../offices/list-offices/) example gets the same list from the Finder.

## Code

```php
$twinfield = Example::twinfield();

// Listing companies is the one ProcessXml request that works without a company in the header.
$result = $twinfield->withoutCompany()->processXml('<list><type>offices</type></list>');

foreach ($result->xpath()->query('/offices/office') ?: [] as $office) {
    assert($office instanceof DOMElement);

    echo $office->textContent, ' ', $office->getAttribute('name'), PHP_EOL;
}
```

## Run

```sh
php examples/process-xml/list-companies/example.php
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
1000 Example Holding B.V.
1001 Example Retail B.V.
1002 Example Services B.V.
```
