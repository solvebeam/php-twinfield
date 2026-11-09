<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// In your own application: your sales day book, customer, revenue account and VAT code.
$dayBook = 'VRK';
$customer = Example::customerCodes($twinfield, 1)[0];
$revenue = Example::profitAndLossAccount($twinfield);
$vatCode = $twinfield->vatCodes()->get(Example::salesVatCode($twinfield));
$date = new DateTimeImmutable('today');

// The invoice was made in your own software: book its amounts.
$net = Amount::fromString('100.00');
$vat = $vatCode->vatOn($net, $date);
$gross = $net->plus($vat);

$xml = XmlBuilder::create('transaction', ['destiny' => 'temporary']);

$header = $xml->add($xml->root(), 'header');
$xml->add($header, 'office', (string) $twinfield->companyCode());
$xml->add($header, 'code', XmlValue::code($dayBook));
$xml->add($header, 'date', XmlValue::date($date));
$xml->add($header, 'duedate', XmlValue::date($date->modify('+30 days')));
$xml->add($header, 'invoicenumber', 'EXAMPLE-' . $date->format('Ymd'));

$lines = $xml->add($xml->root(), 'lines');

// Total line: what the customer owes, including VAT. Debit for a sale, credit for a credit note.
// Without dim1, Twinfield uses the accounts receivable account of the customer.
$total = $xml->add($lines, 'line', attributes: ['type' => 'total', 'id' => '1']);
$xml->add($total, 'dim2', $customer);
$xml->add($total, 'debitcredit', 'debit');
$xml->add($total, 'value', (string) $gross);

// Detail line: the revenue, without VAT. Credit for a sale, debit for a credit note.
// Twinfield adds the VAT line itself.
$detail = $xml->add($lines, 'line', attributes: ['type' => 'detail', 'id' => '2']);
$xml->add($detail, 'dim1', $revenue);
$xml->add($detail, 'debitcredit', 'credit');
$xml->add($detail, 'value', (string) $net);
$xml->add($detail, 'vatcode', $vatCode->code);
$xml->add($detail, 'description', 'Example sale');

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to create it as provisional transaction and delete it again:', PHP_EOL, PHP_EOL;
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
$number = $xpath->evaluate('string(/transaction/header/number)');

echo 'Created:  ', $dayBook, ' ', $number, ' (', $xpath->evaluate('string(/transaction/@location)'), ')', PHP_EOL;

foreach ($xpath->query('/transaction/lines/line') ?: [] as $line) {
    echo '  ', str_pad($xpath->evaluate('string(@type)', $line), 7),
        ' ', str_pad($xpath->evaluate('string(dim1)', $line), 6),
        ' ', str_pad($xpath->evaluate('string(dim2)', $line), 6),
        ' ', str_pad($xpath->evaluate('string(debitcredit)', $line), 6),
        ' ', $xpath->evaluate('string(value)', $line), PHP_EOL;
}

// Clean up: delete the provisional example transaction again.
$delete = XmlBuilder::create('transaction', ['action' => 'delete', 'reason' => 'php-twinfield example']);
$delete->add($delete->root(), 'office', (string) $twinfield->companyCode());
$delete->add($delete->root(), 'code', $dayBook);
$delete->add($delete->root(), 'number', $number);

$twinfield->processXml($delete->toXml());

echo 'Deleted:  ', $dayBook, ' ', $number, PHP_EOL;
