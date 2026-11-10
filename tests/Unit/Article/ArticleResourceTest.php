<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Article;

use DOMDocument;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Article\ArticleAlreadyExistsException;
use SolveBeam\Twinfield\Article\ArticleInput;
use SolveBeam\Twinfield\Article\ArticleNotFoundException;
use SolveBeam\Twinfield\Article\ArticleStatus;
use SolveBeam\Twinfield\Article\ArticleSummary;
use SolveBeam\Twinfield\Article\ArticleType;
use SolveBeam\Twinfield\Article\ArticleVat;
use SolveBeam\Twinfield\Article\SubArticleInput;
use SolveBeam\Twinfield\Article\SubArticleStatus;
use SolveBeam\Twinfield\Article\SubArticleSummary;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class ArticleResourceTest extends TestCase
{
    public function testSearchesArticles(): void
    {
        $http = ReplayClient::fixture('finder-articles');

        $page = $this->twinfield($http)
            ->articles()
            ->search('*BIKE*')
            ->inCode()
            ->articleVat(ArticleVat::Exclusive)
            ->limit(10)
            ->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new ArticleSummary(TestTokens::company(), '5DISCOUNT', '5% discount'),
            new ArticleSummary(TestTokens::company(), 'BIKE', 'Bike summer edition'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertSame('ART', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('*BIKE*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('vat', $xpath->evaluate('string(//tw:Search/tw:options/tw:ArrayOfString/tw:string[1])'));
        self::assertSame('exclusive', $xpath->evaluate('string(//tw:Search/tw:options/tw:ArrayOfString/tw:string[2])'));
    }

    public function testSearchSendsNoOptionsByDefault(): void
    {
        $http = ReplayClient::fixture('finder-articles');

        $this->twinfield($http)->articles()->search()->get();

        self::assertSame(
            0.0,
            $this->soapXpath($http->lastRequest())->evaluate('count(//tw:Search/tw:options/tw:ArrayOfString)'),
            'The company comes from the header.',
        );
    }

    public function testListsTheSubArticlesOfAnArticle(): void
    {
        $http = ReplayClient::fixture('finder-sub-articles');

        $subArticles = $this->twinfield($http)->articles()->subArticles('BIKE');

        self::assertEquals([
            new SubArticleSummary(TestTokens::company(), 'BIKE', 'BIKE.L', "Lady's bike summer edition"),
            new SubArticleSummary(TestTokens::company(), 'BIKE', 'BIKE.M', "Man's bike summer edition"),
        ], $subArticles);

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('SAR', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('article', $xpath->evaluate('string(//tw:Search/tw:options/tw:ArrayOfString/tw:string[1])'));
        self::assertSame('BIKE', $xpath->evaluate('string(//tw:Search/tw:options/tw:ArrayOfString/tw:string[2])'));
    }

    public function testTheOnlySubArticleMayHaveNoCode(): void
    {
        $subArticles = $this->twinfield(ReplayClient::fixture('finder-sub-articles-without-code'))
            ->articles()
            ->subArticles('5DISCOUNT');

        self::assertCount(1, $subArticles);
        self::assertNull($subArticles[0]->code);
        self::assertSame('5% discount', $subArticles[0]->name);
    }

    public function testAnUnknownArticleHasNoSubArticles(): void
    {
        self::assertSame(
            [],
            $this->twinfield(ReplayClient::fixture('finder-sub-articles-none'))->articles()->subArticles('NOPE'),
        );
    }

    public function testReadsAnArticleWithItsSubArticles(): void
    {
        $http = ReplayClient::fixture('article-read');

        $article = $this->twinfield($http)->articles()->get('BIKE');

        self::assertSame(
            '<read><type>article</type><office>1000</office><code>BIKE</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertEquals(TestTokens::company('1000'), $article->company);
        self::assertSame('BIKE', $article->code);
        self::assertSame(ArticleStatus::Active, $article->status);
        self::assertSame(ArticleType::Normal, $article->type);
        self::assertSame('Bike summer edition', $article->name);
        self::assertSame('Bike summer', $article->shortName);
        self::assertSame('Piece', $article->unitNameSingular);
        self::assertSame('Pieces', $article->unitNamePlural);
        self::assertSame('VH', $article->vatCode);
        self::assertFalse($article->allowChangeVatCode);
        self::assertNull($article->performanceType, 'An empty element.');
        self::assertFalse($article->allowChangePerformanceType);
        self::assertFalse($article->percentage);
        self::assertTrue($article->allowDiscountOrPremium);
        self::assertFalse($article->allowChangeUnitPrice);
        self::assertFalse($article->allowDecimalQuantity);

        self::assertCount(3, $article->subArticles);
        self::assertCount(2, $article->activeSubArticles());

        $lady = $article->subArticles[0];
        self::assertSame('BIKE.L', $lady->code);
        self::assertSame("Lady's bike summer edition", $lady->name);
        self::assertSame('Lady summer', $lady->shortName);
        self::assertSame(1, $lady->units);
        self::assertSame('750.00', $lady->unitPriceExcl->value);
        self::assertSame('907.50', $lady->unitPriceInc->value);
        self::assertSame('8050', $lady->generalLedgerAccount);
        self::assertSame('Bike lady', $lady->generalLedgerAccountName);
        self::assertNull($lady->costCenter);
        self::assertNull($lady->costCenterName);
        self::assertNull($lady->freeText3);
        self::assertSame(SubArticleStatus::Active, $lady->status);
        self::assertTrue($lady->inUse);

        $man = $article->subArticle('BIKE.M');
        self::assertNotNull($man);
        self::assertNull($man->shortName);
        self::assertSame('00001', $man->costCenter);
        self::assertSame('Sales', $man->costCenterName);
        self::assertSame('Summer', $man->freeText3);
        self::assertFalse($man->inUse);

        $kid = $article->subArticle('BIKE.K');
        self::assertNotNull($kid);
        self::assertSame(SubArticleStatus::Deleted, $kid->status);
        self::assertNull($kid->generalLedgerAccountName, 'An empty name attribute.');
        self::assertNull($article->subArticle('NOPE'));
        self::assertNull($article->subArticle(null));
    }

    public function testReadsADiscountWithoutPerformanceTypeAndSubArticleCode(): void
    {
        $article = $this->twinfield(ReplayClient::fixture('article-read-discount'))->articles()->get('5DISCOUNT');

        self::assertSame(ArticleType::Discount, $article->type);
        self::assertTrue($article->percentage);
        self::assertNull($article->vatCode);
        self::assertNull($article->unitNameSingular);
        self::assertNull($article->unitNamePlural);
        self::assertNull($article->performanceType);
        self::assertNull($article->allowChangePerformanceType, 'Twinfield leaves the element out.');

        self::assertCount(1, $article->subArticles);
        self::assertNull($article->subArticles[0]->code);
        self::assertSame($article->subArticles[0], $article->subArticle(null));
        self::assertSame('5.00', $article->subArticles[0]->unitPriceExcl->value);
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('article-read-not-found'))->articles()->get('NOPE');
            self::fail('Expected an ArticleNotFoundException.');
        } catch (ArticleNotFoundException $exception) {
            self::assertSame('NOPE', $exception->articleCode);
            self::assertSame('Artikel NOPE bestaat niet.', $exception->reason);
            self::assertSame('Article NOPE not found: Artikel NOPE bestaat niet.', $exception->getMessage());
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testFind(): void
    {
        self::assertNull($this->twinfield(ReplayClient::fixture('article-read-not-found'))->articles()->find('NOPE'));
        self::assertSame(
            'BIKE',
            $this->twinfield(ReplayClient::fixture('article-read'))->articles()->find('BIKE')?->code,
        );
    }

    public function testSavesAndReadsBack(): void
    {
        $http = new ReplayClient(ReplayClient::response('article-save'), ReplayClient::response('article-read'));

        $article = $this->twinfield($http)->articles()->save(
            ArticleInput::normal('BIKE', 'Bike summer edition')
                ->shortName('Bike summer')
                ->unitNames('Piece', 'Pieces')
                ->vatCode('VH')
                ->allowChangeVatCode(false)
                ->allowDiscountOrPremium()
                ->allowChangeUnitPrice(false)
                ->allowDecimalQuantity(false)
                ->subArticle(
                    SubArticleInput::create("Lady's bike summer edition", '8050', 'BIKE.L')
                        ->shortName('Lady summer')
                        ->unitPriceExcl('750'),
                )
                ->subArticle(
                    SubArticleInput::create("Man's bike summer edition", '8051', 'BIKE.M')
                        ->unitPriceInc('919.6')
                        ->costCenter('00001')
                        ->freeText3('Summer'),
                ),
        );

        self::assertTrue($article->subArticles[0]->inUse, 'The article is read back after saving.');
        self::assertCount(2, $http->requests);
        self::assertSame(
            '<article><header><office>1000</office><code>BIKE</code><type>normal</type>'
            . '<name>Bike summer edition</name><shortname>Bike summer</shortname>'
            . '<unitnamesingular>Piece</unitnamesingular><unitnameplural>Pieces</unitnameplural>'
            . '<vatcode>VH</vatcode><allowchangevatcode>false</allowchangevatcode>'
            . '<allowdiscountorpremium>true</allowdiscountorpremium>'
            . '<allowchangeunitsprice>false</allowchangeunitsprice>'
            . '<allowdecimalquantity>false</allowdecimalquantity></header><lines>'
            . '<line><unitspriceexcl>750.00</unitspriceexcl><unitspriceinc/><units>1</units>'
            . '<name>Lady\'s bike summer edition</name><shortname>Lady summer</shortname>'
            . '<subcode>BIKE.L</subcode><freetext1>8050</freetext1><freetext2/><freetext3/></line>'
            . '<line><unitspriceexcl/><unitspriceinc>919.60</unitspriceinc><units>1</units>'
            . '<name>Man\'s bike summer edition</name><shortname/>'
            . '<subcode>BIKE.M</subcode><freetext1>8051</freetext1><freetext2>00001</freetext2>'
            . '<freetext3>Summer</freetext3></line></lines></article>',
            $this->xmlRequest($http->requests[0]),
        );
        self::assertSame(
            '<read><type>article</type><office>1000</office><code>BIKE</code></read>',
            $this->xmlRequest($http->requests[1]),
        );
    }

    public function testSaveErrorsAreNotMappedToNotFound(): void
    {
        $this->expectException(ProcessXmlException::class);
        $this->expectExceptionMessage('Btw-code XX bestaat niet.');

        $this->twinfield(ReplayClient::fixture('article-save-error'))->articles()->save(
            ArticleInput::normal('BIKE', 'Bike summer edition')
                ->vatCode('XX')
                ->subArticle(SubArticleInput::create('Bike', '8050')->unitPriceExcl('750')),
        );
    }

    public function testSaveNeedsASubArticle(): void
    {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->articles()->save(ArticleInput::normal('BIKE', 'Bike'));
            self::fail('Expected a LogicException.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('at least one sub-article', $exception->getMessage());
        }

        self::assertCount(0, $http->requests, 'Nothing is sent.');
    }

    public function testCreatesAfterCheckingTheCodeIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('article-read-not-found'),
            ReplayClient::response('article-save'),
            ReplayClient::response('article-read'),
        );

        $article = $this->twinfield($http)->articles()->create(
            ArticleInput::normal('BIKE', 'Bike summer edition')
                ->subArticle(SubArticleInput::create('Bike', '8050')->unitPriceExcl('750')),
        );

        self::assertSame('BIKE', $article->code);
        self::assertCount(3, $http->requests);
        self::assertStringContainsString('<name>Bike summer edition</name>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('article-read');

        try {
            $this->twinfield($http)->articles()->create(
                ArticleInput::normal('BIKE', 'Other')->subArticle(SubArticleInput::create('Other', '8050')),
            );
            self::fail('Expected an ArticleAlreadyExistsException.');
        } catch (ArticleAlreadyExistsException $exception) {
            self::assertSame('Article BIKE already exists.', $exception->getMessage());
            self::assertSame('BIKE', $exception->articleCode);
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testUpdatesAPriceAndKeepsTheRest(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('article-read'),
            ReplayClient::response('article-read'),
            ReplayClient::response('article-save'),
            ReplayClient::response('article-read'),
        );
        $articles = $this->twinfield($http)->articles();
        $article = $articles->get('BIKE');
        $lady = $article->subArticles[0];

        $articles->update(
            $article->edit()
                ->name('Bike winter edition')
                ->subArticle($lady->edit()->unitPriceExcl('799.00'))
                ->removeSubArticle('BIKE.M')
                ->subArticle($article->subArticles[2]->edit()),
        );

        self::assertCount(4, $http->requests);
        self::assertSame(
            '<article><header><office>1000</office><code>BIKE</code><type>normal</type>'
            . '<name>Bike winter edition</name><shortname>Bike summer</shortname>'
            . '<unitnamesingular>Piece</unitnamesingular><unitnameplural>Pieces</unitnameplural>'
            . '<vatcode>VH</vatcode><allowchangevatcode>false</allowchangevatcode>'
            . '<allowchangeperformancetype>false</allowchangeperformancetype><percentage>false</percentage>'
            . '<allowdiscountorpremium>true</allowdiscountorpremium>'
            . '<allowchangeunitsprice>false</allowchangeunitsprice>'
            . '<allowdecimalquantity>false</allowdecimalquantity></header><lines>'
            . '<line><unitspriceexcl>799.00</unitspriceexcl><unitspriceinc/><units>1</units>'
            . '<name>Lady\'s bike summer edition</name><shortname>Lady summer</shortname>'
            . '<subcode>BIKE.L</subcode><freetext1>8050</freetext1><freetext2/><freetext3/></line>'
            . '<line status="deleted"><unitspriceexcl>760.00</unitspriceexcl>'
            . '<unitspriceinc>919.60</unitspriceinc><units>1</units>'
            . '<name>Man\'s bike summer edition</name><shortname/>'
            . '<subcode>BIKE.M</subcode><freetext1>8051</freetext1><freetext2>00001</freetext2>'
            . '<freetext3>Summer</freetext3></line>'
            . '<line status="active"><unitspriceexcl>350.00</unitspriceexcl>'
            . '<unitspriceinc>423.50</unitspriceinc><units>1</units>'
            . '<name>Kid\'s bike summer edition</name><shortname>Kid summer</shortname>'
            . '<subcode>BIKE.K</subcode><freetext1>8052</freetext1><freetext2/><freetext3/></line>'
            . '</lines></article>',
            $this->xmlRequest($http->requests[2]),
        );
    }

    public function testUpdateRefusesAMissingArticle(): void
    {
        $http = ReplayClient::fixture('article-read-not-found');

        try {
            $this->twinfield($http)->articles()->update(
                ArticleInput::normal('NOPE', 'Nope')->subArticle(SubArticleInput::create('Nope', '8050')),
            );
            self::fail('Expected an ArticleNotFoundException.');
        } catch (ArticleNotFoundException) {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testDeletesAnArticle(): void
    {
        $http = new ReplayClient(ReplayClient::response('article-read'), ReplayClient::response('article-delete'));

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->articles()->delete('BIKE'));
        self::assertSame(
            '<article><header status="deleted"><office>1000</office><code>BIKE</code></header></article>',
            $this->xmlRequest($http->requests[1]),
        );
    }

    public function testDeleteHidesAnArticleInUse(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('article-read'),
            ReplayClient::response('article-delete-hidden'),
        );

        self::assertSame(DeleteResult::Hidden, $this->twinfield($http)->articles()->delete('BIKE'));
    }

    public function testDeleteThrowsWhenTheArticleStaysActive(): void
    {
        $http = new ReplayClient(ReplayClient::response('article-read'), ReplayClient::response('article-activate'));

        $this->expectException(UnexpectedResponseException::class);

        $this->twinfield($http)->articles()->delete('BIKE');
    }

    public function testDeleteNeverCreatesAMissingArticle(): void
    {
        $http = ReplayClient::fixture('article-read-not-found');

        try {
            $this->twinfield($http)->articles()->delete('NOPE');
            self::fail('Expected an ArticleNotFoundException.');
        } catch (ArticleNotFoundException) {
            self::assertCount(1, $http->requests, 'No <article> request is sent.');
        }
    }

    public function testActivatesAnArticle(): void
    {
        $http = new ReplayClient(ReplayClient::response('article-read'), ReplayClient::response('article-activate'));

        $this->twinfield($http)->articles()->activate('BIKE');

        self::assertSame(
            '<article><header status="active"><office>1000</office><code>BIKE</code></header></article>',
            $this->xmlRequest($http->requests[1]),
        );
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider(), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);

        $twinfield->articles()->get('BIKE');
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(
            TestTokens::provider('https://example.test'),
            $http,
            $psr17,
            $psr17,
        )->company('1000');
    }

    private function soapXpath(RequestInterface $request): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML((string) $request->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        return $xpath;
    }

    private function xmlRequest(RequestInterface $request): string
    {
        $xmlRequest = $this->soapXpath($request)->evaluate('string(//tw:ProcessXmlString/tw:xmlRequest)');

        self::assertIsString($xmlRequest);

        return $xmlRequest;
    }
}
