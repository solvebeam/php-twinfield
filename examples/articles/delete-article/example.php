<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Article\ArticleInput;
use SolveBeam\Twinfield\Article\SubArticleInput;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$articles = $twinfield->articles();
$code = Example::unusedArticleCode($twinfield);

// An article to delete.
$articles->create(
    ArticleInput::normal($code, 'Example bike')
        ->vatCode(Example::salesVatCode($twinfield))
        ->subArticle(
            SubArticleInput::create('Example bike', Example::profitAndLossAccount($twinfield))->unitPriceExcl('750.00'),
        ),
);

echo 'Created ', $code, PHP_EOL;

// Twinfield hides an article that is used in an invoice instead of deleting it.
$result = $articles->delete($code);

echo $result === DeleteResult::Deleted ? 'Deleted ' : 'Hidden ', $code, ': status ';
echo $articles->get($code)->status->value, PHP_EOL;

// A deleted or hidden article can be activated again.
$articles->activate($code);

echo 'Activated ', $code, ': status ', $articles->get($code)->status->value, PHP_EOL;

// Clean up.
$articles->delete($code);

echo 'Deleted ', $code, PHP_EOL;
