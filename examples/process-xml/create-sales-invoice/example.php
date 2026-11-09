<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// In your own application: your invoice type, customer, article and VAT code.
$invoiceType = 'FACTUUR';
$customer = Example::customerCodes($twinfield, 1)[0];
$article = '0';
$vatCode = Example::salesVatCode($twinfield);

$xml = XmlBuilder::create('salesinvoice');

$header = $xml->add($xml->root(), 'header');
$xml->add($header, 'office', (string) $twinfield->companyCode());
$xml->add($header, 'invoicetype', $invoiceType);
$xml->add($header, 'customer', $customer);
// concept: provisional, can still be changed. final: no changes anymore, and Twinfield books
// the sales transaction.
$xml->add($header, 'status', 'concept');
$xml->add($header, 'paymentmethod', 'bank');

$lines = $xml->add($xml->root(), 'lines');

// Twinfield calculates the amounts, the VAT and the totals from the article.
$line = $xml->add($lines, 'line', attributes: ['id' => '1']);
$xml->add($line, 'article', $article);
$xml->add($line, 'quantity', '1');
$xml->add($line, 'units', '1');
// Only when the price of the article may be changed.
$xml->add($line, 'unitspriceexcl', '100.00');
$xml->add($line, 'vatcode', $vatCode);
$xml->add($line, 'description', 'Example sale');

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to create it as concept invoice (it is not deleted again):', PHP_EOL, PHP_EOL;
    echo Example::xml($xml), PHP_EOL;

    exit(0);
}

try {
    $result = $twinfield->processXml($xml->toXml());
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

$xpath = $result->xpath();

echo 'Invoice:  ', $xpath->evaluate('string(/salesinvoice/header/invoicenumber)'),
    ' (', $xpath->evaluate('string(/salesinvoice/header/status)'), ')', PHP_EOL;
echo 'Excl.:    ', $xpath->evaluate('string(/salesinvoice/totals/valueexcl)'), PHP_EOL;
echo 'Incl.:    ', $xpath->evaluate('string(/salesinvoice/totals/valueinc)'), PHP_EOL;

// Only for a final invoice: the sales transaction Twinfield booked.
if ($xpath->evaluate('count(/salesinvoice/financials)') > 0) {
    echo 'Booked:   ', $xpath->evaluate('string(/salesinvoice/financials/code)'),
        ' ', $xpath->evaluate('string(/salesinvoice/financials/number)'), PHP_EOL;
}
