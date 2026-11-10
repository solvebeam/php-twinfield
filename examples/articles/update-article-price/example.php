<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Article\ArticleInput;
use SolveBeam\Twinfield\Article\SubArticleInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$articles = $twinfield->articles();
$code = Example::unusedArticleCode($twinfield);
$account = Example::profitAndLossAccount($twinfield);

// An article to change.
$article = $articles->create(
    ArticleInput::normal($code, 'Example bike')
        ->vatCode(Example::salesVatCode($twinfield))
        ->subArticle(SubArticleInput::create("Example lady's bike", $account, $code . '-L')->unitPriceExcl('750.00')),
);

echo 'Created ', $article->code, ': ', $article->subArticles[0]->code, ' ';
echo $article->subArticles[0]->unitPriceExcl, PHP_EOL;

// edit() has all current values: change the price of the sub-article and add a second one.
$updated = $articles->update(
    $article->edit()
        ->subArticle($article->subArticles[0]->edit()->unitPriceExcl('799.00'))
        ->subArticle(SubArticleInput::create("Example man's bike", $account, $code . '-M')->unitPriceExcl('810.00')),
);

foreach ($updated->subArticles as $subArticle) {
    echo 'Updated ', $subArticle->code, ': ', $subArticle->unitPriceExcl, ' excl., ';
    echo $subArticle->unitPriceInc, ' incl.', PHP_EOL;
}

// Clean up: delete the example article again.
$articles->delete($code);

echo 'Deleted ', $code, PHP_EOL;
