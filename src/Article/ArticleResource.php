<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

use LogicException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Articles (items) of the Classic invoicing module, with their sub-articles. A sales invoice line
 * refers to an article and one of its sub-articles: the article has the VAT code and what may be
 * changed on the invoice, the sub-article the price and the revenue account.
 *
 *   $articles = $twinfield->company('1000')->articles();
 *
 *   foreach ($articles->search('BIKE*')->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $article = $articles->get('BIKE');
 *
 *   foreach ($article->subArticles as $subArticle) {
 *       echo $subArticle->code, ' ', $subArticle->unitPriceExcl, PHP_EOL;
 *   }
 *
 *   $articles->create(
 *       ArticleInput::normal('BIKE', 'Bike summer edition')
 *           ->vatCode('VH')
 *           ->subArticle(SubArticleInput::create("Lady's bike", '8050', 'BIKE.L')->unitPriceExcl('750.00')),
 *   );
 *   $articles->update(
 *       $article->edit()->subArticle($article->subArticles[0]->edit()->unitPriceExcl('799.00')),
 *   );
 *   $articles->delete('BIKE');
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * These are not the sales articles of the REST API (`salesArticles()`): those are a separate list.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/articles/
 */
final readonly class ArticleResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search articles by code and name, e.g. `search('BIKE*')->inCode()->get()`.
     *
     * Finder type `ART`; the company comes from the client, so no `office` option is sent.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<ArticleSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        $query = $this->twinfield->finder(FinderType::Articles);

        return CodeNameSearch::forCompany($query, ArticleSummary::fromFinderRow(...))->pattern($pattern);
    }

    /**
     * Code and name of the sub-articles of an article (Finder type `SAR`), e.g. to fill a list for
     * an invoice line. Empty when the article does not exist: the Finder gives no error then.
     *
     * @return list<SubArticleSummary>
     */
    public function subArticles(string $articleCode): array
    {
        $query = $this->twinfield->finder(FinderType::SubItems)->option('article', $articleCode);
        $company = $query->companyReference();
        $subArticles = [];

        foreach ($query->all() as $index => $row) {
            $subArticles[] = SubArticleSummary::fromFinderRow($row, $index, $company, $articleCode);
        }

        return $subArticles;
    }

    /**
     * The article with its settings and all its sub-articles.
     *
     * @throws ArticleNotFoundException When there is no article with this code.
     */
    public function get(string $code): Article
    {
        try {
            $result = $this->twinfield->processXml(ArticleXmlWriter::read($this->company(), $code)->toXml());
        } catch (ProcessXmlException $exception) {
            throw ArticleNotFoundException::fromProcessXml($exception, $code) ?? $exception;
        }

        return ArticleXmlReader::article(XmlElement::root($result), $this->twinfield->companyReference());
    }

    /**
     * The article, or null when there is no article with this code.
     */
    public function find(string $code): ?Article
    {
        try {
            return $this->get($code);
        } catch (ArticleNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new article. Reads first, so an existing article is never overwritten.
     *
     * @throws ArticleAlreadyExistsException When the code is already in use.
     */
    public function create(ArticleInput $article): Article
    {
        if ($this->find($article->code) !== null) {
            throw new ArticleAlreadyExistsException($article->code);
        }

        return $this->save($article);
    }

    /**
     * Updates an existing article with all values of the input. Start from `$article->edit()` to
     * keep the values and sub-articles you do not change.
     *
     * @throws ArticleNotFoundException When there is no article with this code.
     */
    public function update(ArticleInput $article): Article
    {
        $this->get($article->code);

        return $this->save($article);
    }

    /**
     * Creates the article, or updates it when the code exists (no existence check). Reads it back
     * afterwards, so you always get the complete article.
     *
     * @throws ProcessXmlException When Twinfield refuses the article.
     * @throws LogicException      When the input has no sub-articles.
     */
    public function save(ArticleInput $article): Article
    {
        $this->twinfield->processXml(ArticleXmlWriter::save($article, $this->company())->toXml());

        return $this->get($article->code);
    }

    /**
     * Deletes the article. When it is used in an invoice, Twinfield hides it instead.
     *
     * @throws ArticleNotFoundException When there is no article with this code.
     */
    public function delete(string $code): DeleteResult
    {
        $status = $this->changeStatus($code, ArticleStatus::Deleted);

        return match ($status) {
            ArticleStatus::Deleted => DeleteResult::Deleted,
            ArticleStatus::Hidden => DeleteResult::Hidden,
            ArticleStatus::Active => throw new UnexpectedResponseException(sprintf(
                'Article %s is still active after deleting it.',
                $code,
            )),
        };
    }

    /**
     * Makes a deleted or hidden article active again.
     *
     * @throws ArticleNotFoundException When there is no article with this code.
     */
    public function activate(string $code): void
    {
        $this->changeStatus($code, ArticleStatus::Active);
    }

    private function changeStatus(string $code, ArticleStatus $status): ArticleStatus
    {
        // An `<article>` request creates an article that does not exist, so check first.
        $this->get($code);

        $result = $this->twinfield->processXml(
            ArticleXmlWriter::changeStatus($this->company(), $code, $status)->toXml(),
        );

        return ArticleXmlReader::status(XmlElement::root($result)->child('header'));
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Articles belong to a company: use company() first.');
    }
}
