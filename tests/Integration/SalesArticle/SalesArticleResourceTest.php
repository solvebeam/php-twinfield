<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\SalesArticle;

use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Rest\FileType;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live REST API sales articles (read-only) of TWINFIELD_COMPANY.
 */
final class SalesArticleResourceTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireScopes(Scope::ReadArticles);
    }

    public function testSearchesAndReadsArticles(): void
    {
        $articles = iterator_to_array($this->twinfield->salesArticles()->search()->all(), false);

        if ($articles === []) {
            self::markTestSkipped('The company has no sales articles.');
        }

        self::assertEquals($articles[0], $this->twinfield->salesArticles()->get($articles[0]->id));
    }

    public function testListsCulturesAndExports(): void
    {
        self::assertNotEmpty($this->twinfield->salesArticles()->cultures());

        $csv = $this->twinfield->salesArticles()->export();

        self::assertSame('articles.csv', $csv->fileName);
        self::assertStringStartsWith('Name,Code,NetPrice,GrossPrice,VatCode,RevenueTypeCode,CostCenterCode,ArticleId', $csv->contents);
        self::assertStringStartsWith('PK', $this->twinfield->salesArticles()->export(FileType::Xlsx)->contents);
    }
}
