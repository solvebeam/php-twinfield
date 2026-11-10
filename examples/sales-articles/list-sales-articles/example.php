<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$vatCodes = [];

foreach ($twinfield->vatCodes()->searchDetails()->all() as $vatCode) {
    $vatCodes[$vatCode->id] = $vatCode->code;
}

foreach ($twinfield->salesArticles()->search($argv[1] ?? '*')->orderBy('name')->all() as $article) {
    echo sprintf(
        '%s %s: %s excl. VAT, %s incl. VAT (VAT code %s)',
        $article->code ?? '(no code)',
        $article->name,
        $article->netPrice,
        $article->grossPrice,
        $vatCodes[$article->vatCodeId] ?? $article->vatCodeId,
    ), PHP_EOL;

    foreach ($article->translations as $culture => $name) {
        echo '  ', $culture, ': ', $name, PHP_EOL;
    }
}
