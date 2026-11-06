<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$code = $argv[1] ?? '1000';

// Build the request with XmlBuilder (DOM), so values are escaped.
$request = XmlBuilder::create('read');
$request->add($request->root(), 'type', 'dimensions');
$request->add($request->root(), 'office', (string) $twinfield->companyCode());
$request->add($request->root(), 'dimtype', 'DEB');
$request->add($request->root(), 'code', $code);

try {
    $result = $twinfield->processXml($request->document());
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
