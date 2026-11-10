<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Article\ArticleInput;
use SolveBeam\Twinfield\Article\SubArticleInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$articles = $twinfield->articles();
$code = $argv[1] ?? Example::unusedArticleCode($twinfield);
// The revenue account and VAT code: from the command line, or else the first ones of the company.
$account = $argv[2] ?? Example::profitAndLossAccount($twinfield);
$vatCode = $argv[3] ?? Example::salesVatCode($twinfield);

$article = $articles->create(
    ArticleInput::normal($code, 'Example bike')
        ->shortName('Example bike')
        ->unitNames('Piece', 'Pieces')
        ->vatCode($vatCode)
        ->subArticle(SubArticleInput::create("Example lady's bike", $account, $code . '-L')->unitPriceExcl('750.00'))
        ->subArticle(SubArticleInput::create("Example man's bike", $account, $code . '-M')->unitPriceExcl('760.00')),
);

echo 'Created ', $article->code, ' ', $article->name, ' (VAT code ', $article->vatCode, ')', PHP_EOL;

foreach ($article->subArticles as $subArticle) {
    echo '  ', $subArticle->code, ' ', $subArticle->name, ': ', $subArticle->unitPriceExcl, ' excl., ';
    echo $subArticle->unitPriceInc, ' incl.', PHP_EOL;
}

// Clean up: delete the example article again.
$articles->delete($article->code);

echo 'Deleted ', $article->code, PHP_EOL;
