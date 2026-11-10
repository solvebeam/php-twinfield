<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Matching\MatchInput;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `KAS:202600014:2 VRK:202600070:1:119.00`: day book, number, line and an optional match value.
$lines = array_slice($argv ?? [], 1);

if (count($lines) < 2) {
    echo 'Usage: php example.php <day book>:<number>:<line>[:<match value>] …', PHP_EOL;

    exit(0);
}

$input = MatchInput::customers(new DateTimeImmutable('today'));

foreach ($lines as $line) {
    [$dayBook, $number, $lineNumber, $matchValue] = array_pad(explode(':', $line), 4, null);

    /** @var numeric-string|null $matchValue */
    $input = $input->line((string) $dayBook, (int) $number, (int) $lineNumber, $matchValue);
}

try {
    $twinfield->matching()->match($input);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

foreach ($input->lines() as $line) {
    $amount = $line->matchValue === null ? 'open amount' : (string) $line->matchValue;

    echo 'Matched ', $line->dayBook, ' ', $line->number, ' line ', $line->line, ': ', $amount, PHP_EOL;
}
