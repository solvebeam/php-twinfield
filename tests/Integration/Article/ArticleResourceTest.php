<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Article;

use SolveBeam\Twinfield\Article\ArticleNotFoundException;
use SolveBeam\Twinfield\Article\ArticleVat;
use SolveBeam\Twinfield\Article\ArticleXmlWriter;
use SolveBeam\Twinfield\Article\SubArticle;
use SolveBeam\Twinfield\Article\SubArticleSummary;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live article searches and reads (read-only) against TWINFIELD_COMPANY.
 */
final class ArticleResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsArticles(): void
    {
        $articles = $this->twinfield->articles();
        $page = $articles->search()->limit(5)->get();

        if ($page->totalRows === 0) {
            self::markTestSkipped('The company has no articles.');
        }

        self::assertSame($page->totalRows, $articles->search()->count());

        foreach ($page as $summary) {
            usleep(300_000);
            $article = $articles->get($summary->code);

            self::assertSame($summary->code, $article->code);
            self::assertSame($summary->name, $article->name);
            self::assertSame($this->twinfield->companyCode(), $article->company->code);
            self::assertNotEmpty($article->subArticles);

            // The Finder gives the same sub-articles as the article itself, without the deleted ones.
            usleep(300_000);
            $code = static fn (SubArticle|SubArticleSummary $subArticle): ?string => $subArticle->code;

            self::assertEqualsCanonicalizing(
                array_map($code, $article->activeSubArticles()),
                array_map($code, $articles->subArticles($summary->code)),
            );

            // edit() must give an input that Twinfield accepts; here only that it builds.
            self::assertStringContainsString(
                '<code>' . htmlspecialchars($article->code, ENT_XML1) . '</code>',
                ArticleXmlWriter::save($article->edit(), 'X')->toXml(),
            );
        }
    }

    public function testTheVatOptionAddsTheSpecialItems(): void
    {
        $articles = $this->twinfield->articles();
        $page = $articles->search()->articleVat(ArticleVat::Exclusive)->limit(5)->get();

        self::assertGreaterThanOrEqual($articles->search()->count(), $page->totalRows);
    }

    public function testAnUnknownArticleHasNoSubArticles(): void
    {
        self::assertSame([], $this->twinfield->articles()->subArticles('SDKMISSING9'));
    }

    public function testThrowsNotFound(): void
    {
        $this->expectException(ArticleNotFoundException::class);

        $this->twinfield->articles()->get('SDKMISSING9');
    }
}
