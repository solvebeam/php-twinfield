<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$since = new DateTimeImmutable($argv[1] ?? '2000-01-01');

foreach ($twinfield->postingRules()->modifiedSince($since)->all() as $rule) {
    echo sprintf(
        '%s rule of %s: %s%s, %d line(s)',
        $rule->type->value,
        $rule->accountId,
        $rule->description ?? '(no description)',
        $rule->amount === null ? '' : ' for ' . $rule->amount,
        count($rule->lines),
    ), PHP_EOL;

    foreach ($rule->lines as $line) {
        echo '  account ', $line->generalLedgerAccountId ?? '(none)';
        echo $line->ratio === null ? '' : ', ratio ' . $line->ratio, PHP_EOL;
    }
}
