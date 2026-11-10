<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Article;

use SolveBeam\Twinfield\Article\ArticleAlreadyExistsException;
use SolveBeam\Twinfield\Article\ArticleInput;
use SolveBeam\Twinfield\Article\ArticleStatus;
use SolveBeam\Twinfield\Article\ArticleType;
use SolveBeam\Twinfield\Article\SubArticleInput;
use SolveBeam\Twinfield\Article\SubArticleStatus;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates, updates, deletes and reactivates a temporary article SDKTEST9xx; always deletes it again.
 * The revenue account and VAT code come from an existing article of the company.
 */
final class ArticleResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $articles = $this->twinfield->articles();
        [$account, $vatCode] = $this->accountAndVatCode();
        $code = $this->unusedCode();

        try {
            $created = $articles->create(
                ArticleInput::normal($code, 'SDK test article')
                    ->shortName('SDK test')
                    ->unitNames('Piece', 'Pieces')
                    ->vatCode($vatCode)
                    ->subArticle(
                        SubArticleInput::create('SDK test lady', $account, $code . '-L')->unitPriceExcl('750.00'),
                    )
                    ->subArticle(
                        SubArticleInput::create('SDK test man', $account, $code . '-M')->unitPriceExcl('760.00'),
                    ),
            );

            self::assertSame($code, $created->code);
            self::assertSame(ArticleStatus::Active, $created->status);
            self::assertSame(ArticleType::Normal, $created->type);
            self::assertSame('SDK test', $created->shortName);
            self::assertSame('Piece', $created->unitNameSingular);
            self::assertSame($vatCode, $created->vatCode);
            self::assertCount(2, $created->subArticles);
            $lady = $created->subArticle($code . '-L');
            self::assertNotNull($lady);
            self::assertSame('750.00', $lady->unitPriceExcl->value);
            self::assertFalse($created->subArticles[0]->inUse);
            self::assertSame($account, $created->subArticles[0]->generalLedgerAccount);

            try {
                $articles->create(
                    ArticleInput::normal($code, 'Duplicate')
                        ->subArticle(SubArticleInput::create('Duplicate', $account)),
                );
                self::fail('Expected an ArticleAlreadyExistsException.');
            } catch (ArticleAlreadyExistsException) {
                self::assertSame('SDK test article', $articles->get($code)->name, 'The existing article is unchanged.');
            }

            $updated = $articles->update(
                $created->edit()
                    ->name('SDK test article (updated)')
                    ->shortName(null)
                    ->subArticle($lady->edit()->unitPriceExcl('799.00')),
            );

            self::assertSame('SDK test article (updated)', $updated->name);
            self::assertNull($updated->shortName, 'An update clears values that were removed.');
            self::assertCount(2, $updated->subArticles, 'The existing sub-article is changed, not added again.');
            self::assertSame('799.00', $updated->subArticle($code . '-L')?->unitPriceExcl->value);
            self::assertSame('760.00', $updated->subArticle($code . '-M')?->unitPriceExcl->value);

            $removed = $articles->update($updated->edit()->removeSubArticle($code . '-M'));

            self::assertCount(1, $removed->activeSubArticles(), 'A line with status deleted is removed.');
            self::assertNotSame(SubArticleStatus::Active, $removed->subArticle($code . '-M')?->status);

            self::assertSame(DeleteResult::Deleted, $articles->delete($code));
            self::assertSame(ArticleStatus::Deleted, $articles->get($code)->status);

            $articles->activate($code);
            self::assertSame(ArticleStatus::Active, $articles->get($code)->status);
        } finally {
            if ($articles->find($code)?->status === ArticleStatus::Active) {
                $articles->delete($code);
            }
        }
    }

    /**
     * @return array{string, string} General ledger account and VAT code of an existing article.
     */
    private function accountAndVatCode(): array
    {
        $articles = $this->twinfield->articles();

        foreach ($articles->search()->limit(25)->get() as $summary) {
            usleep(300_000);
            $article = $articles->get($summary->code);

            if ($article->type === ArticleType::Normal && $article->vatCode !== null) {
                return [$article->subArticles[0]->generalLedgerAccount, $article->vatCode];
            }
        }

        self::markTestSkipped('The company has no ordinary article with a VAT code to take an account from.');
    }

    private function unusedCode(): string
    {
        for ($number = 999; $number > 900; $number--) {
            $code = sprintf('SDKTEST%03d', $number);

            if ($this->twinfield->articles()->find($code) === null) {
                return $code;
            }
        }

        self::fail('No unused article code SDKTEST901–SDKTEST999.');
    }
}
