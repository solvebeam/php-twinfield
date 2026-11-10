<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\SalesArticle\SalesArticleInput;
use SolveBeam\Twinfield\VatCode\VatCodeType;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$articles = $twinfield->salesArticles();

$vatCode = $twinfield->vatCodes()->searchDetails()->type(VatCodeType::Sales)->limit(1)->get()->items[0];

$article = $articles->create(
    SalesArticleInput::create('Example bike', $vatCode->id)
        ->code('EXAMPLE-' . random_int(1000, 9999))
        ->netPrice('825.00')
        ->translations(['en-GB' => 'Example bike', 'nl-NL' => 'Voorbeeldfiets']),
);

echo 'Created ', $article->code, ' ', $article->name, ': ', $article->netPrice, ' excl. VAT, ';
echo $article->grossPrice, ' incl. VAT ', $vatCode->code, PHP_EOL;

$article = $articles->update($article->edit()->grossPrice('999.00'));

echo 'Updated to ', $article->grossPrice, ' incl. VAT (version ', $article->version, ')', PHP_EOL;

$articles->delete($article->id, $article->version);

echo 'Deleted ', $article->code, PHP_EOL;
