<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Listing companies is the one ProcessXml request that works without a company in the header.
$result = $twinfield->withoutCompany()->processXml('<list><type>offices</type></list>');

foreach ($result->xpath()->query('/offices/office') ?: [] as $office) {
    assert($office instanceof DOMElement);

    echo $office->textContent, ' ', $office->getAttribute('name'), PHP_EOL;
}
