<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Article\ArticleNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first article.
$code = $argv[1] ?? $twinfield->articles()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no articles.' . PHP_EOL);

try {
    $article = $twinfield->articles()->get($code);
} catch (ArticleNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Code:      ', $article->code, PHP_EOL;
echo 'Name:      ', $article->name, PHP_EOL;
echo 'Status:    ', $article->status->value, PHP_EOL;
echo 'Type:      ', $article->type->value, $article->percentage ? ' (percentage)' : '', PHP_EOL;
echo 'VAT code:  ', $article->vatCode ?? '–', PHP_EOL;
echo 'Unit:      ', $article->unitNameSingular ?? '–', ' / ', $article->unitNamePlural ?? '–', PHP_EOL;

foreach ($article->subArticles as $subArticle) {
    echo 'Sub:       ', $subArticle->code ?? '(no code)', ' ', $subArticle->name, ': ';
    echo $subArticle->unitPriceExcl, ' excl., ', $subArticle->unitPriceInc, ' incl., ';
    echo 'account ', $subArticle->generalLedgerAccount, ', ', $subArticle->status->value;
    echo $subArticle->inUse ? ' (in use)' : '', PHP_EOL;
}
