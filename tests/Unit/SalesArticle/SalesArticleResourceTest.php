<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\SalesArticle;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Rest\FileType;
use SolveBeam\Twinfield\Rest\ValidationException;
use SolveBeam\Twinfield\Rest\VersionConflictException;
use SolveBeam\Twinfield\SalesArticle\SalesArticle;
use SolveBeam\Twinfield\SalesArticle\SalesArticleInput;
use SolveBeam\Twinfield\SalesArticle\SearchScope;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class SalesArticleResourceTest extends TestCase
{
    private const ARTICLE_ID = 'c8399ffa-1ace-d26b-0e98-d06f96dc3691';
    private const VAT_CODE_ID = '439518ff-cec0-3268-535d-dc208afe8f62';

    public function testSearchesArticles(): void
    {
        $http = ReplayClient::jsonFixture('sales-articles-list');

        $page = TestTokens::restClient($http)->salesArticles()->search('1*')
            ->scope(SearchScope::AnyTranslation)
            ->inCulture('nl-NL')
            ->vatCodes(self::VAT_CODE_ID, '0f8fad5b-d9cb-469f-a165-70867728950e')
            ->grossPriceBetween('10', null)
            ->orderBy('name')
            ->get();

        self::assertSame(8, $page->total);

        $article = $page->items[0];

        self::assertSame(self::ARTICLE_ID, $article->id);
        self::assertNull($article->code, 'Twinfield sends an empty code.');
        self::assertSame('12.0000', (string) $article->netPrice);
        self::assertSame('14.4100', (string) $article->grossPrice);
        self::assertSame(self::VAT_CODE_ID, $article->vatCodeId);
        self::assertNull($article->costCenterId);
        self::assertSame([], $article->translations);
        self::assertSame(2, $article->version);
        self::assertSame(
            TestTokens::REST_COMPANY_URL
            . '/sales/articles?searchPattern=1%2A&searchScope=anyTranslation&customerCultureCode=nl-NL'
            . '&vats=' . self::VAT_CODE_ID . '&vats=0f8fad5b-d9cb-469f-a165-70867728950e'
            . '&grossPriceFrom=10.0000&orderBy=name&asc=true',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testCreatesAnArticle(): void
    {
        $http = new ReplayClient(ReplayClient::jsonResponse('sales-articles-get', 201));

        $article = TestTokens::restClient($http)->salesArticles()->create(
            SalesArticleInput::create('Bike', self::VAT_CODE_ID)->code('BIKE')->netPrice('825')->translations(
                ['en-GB' => 'Bike'],
            ),
        );

        $request = $http->lastRequest();

        self::assertSame(self::ARTICLE_ID, $article->id);
        self::assertSame('POST', $request->getMethod());
        self::assertSame(TestTokens::REST_COMPANY_URL . '/sales/articles', (string) $request->getUri());
        self::assertSame(
            '{"name":"Bike","code":"BIKE","vatId":"' . self::VAT_CODE_ID . '",'
                . '"translations":{"en-GB":"Bike"},"netPrice":825.0000}',
            (string) $request->getBody(),
        );
    }

    public function testReadsACreatedArticleWithoutBodyByItsId(): void
    {
        $http = new ReplayClient(
            ReplayClient::raw(201, '', ['Id' => self::ARTICLE_ID]),
            ReplayClient::jsonResponse('sales-articles-get'),
        );

        $article = TestTokens::restClient($http)->salesArticles()->create(
            SalesArticleInput::create('Bike', self::VAT_CODE_ID)->grossPrice('998.25'),
        );

        self::assertSame(self::ARTICLE_ID, $article->id);
        self::assertStringContainsString('"grossPrice":998.2500', (string) $http->requests[0]->getBody());
        self::assertStringNotContainsString('netPrice', (string) $http->requests[0]->getBody());
        self::assertStringEndsWith('/sales/articles/' . self::ARTICLE_ID, (string) $http->requests[1]->getUri());
    }

    public function testUpdatesAnArticleWithItsVersion(): void
    {
        $http = new ReplayClient(
            ReplayClient::jsonResponse('sales-articles-get'),
            ReplayClient::jsonResponse('sales-articles-get'),
        );
        $articles = TestTokens::restClient($http)->salesArticles();

        $article = $articles->get(self::ARTICLE_ID);
        $articles->update($article->edit()->name('Grass')->netPrice('13'));

        $request = $http->lastRequest();
        $body = json_decode((string) $request->getBody(), true);

        self::assertSame('PUT', $request->getMethod());
        self::assertStringEndsWith('/sales/articles/' . self::ARTICLE_ID, (string) $request->getUri());
        self::assertSame('2', $request->getHeaderLine('If-Match'));
        self::assertIsArray($body);
        self::assertSame('Grass', $body['name']);
        self::assertSame('', $body['code']);
        self::assertSame(self::ARTICLE_ID, $body['id']);
        self::assertSame(2, $body['version']);
        self::assertSame('8b0dd650-9456-377b-9a27-1b2749e8de54', $body['revenueTypeId']);
        self::assertStringContainsString('"netPrice":13.0000', (string) $request->getBody());
    }

    public function testThrowsAVersionConflict(): void
    {
        $article = self::article();

        $this->expectException(VersionConflictException::class);

        TestTokens::restClient(
            new ReplayClient(
                ReplayClient::raw(412, '"Article with specified version was modified since last request"'),
            ),
        )
            ->salesArticles()->update($article->edit()->name('Old'));
    }

    public function testThrowsWhenTheCodeIsInUse(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Article code is already in use');

        TestTokens::restClient(new ReplayClient(ReplayClient::raw(422, '"Article code is already in use"')))
            ->salesArticles()->create(SalesArticleInput::create('Bike', self::VAT_CODE_ID)->code('BIKE'));
    }

    public function testDeletesAnArticle(): void
    {
        $http = new ReplayClient(ReplayClient::raw(204), ReplayClient::raw(204));
        $articles = TestTokens::restClient($http)->salesArticles();

        $articles->delete(self::ARTICLE_ID, 3);
        $articles->delete(self::ARTICLE_ID);

        self::assertSame('DELETE', $http->requests[0]->getMethod());
        self::assertSame('3', $http->requests[0]->getHeaderLine('If-Match'));
        self::assertSame('*', $http->requests[1]->getHeaderLine('If-Match'));
    }

    public function testListsCultures(): void
    {
        $http = ReplayClient::jsonFixture('sales-articles-cultures');

        $cultures = TestTokens::restClient($http)->salesArticles()->cultures();

        self::assertCount(6, $cultures);
        self::assertSame('en-GB', $cultures[0]->code);
        self::assertStringEndsWith('/sales/articlecultures', (string) $http->lastRequest()->getUri());
    }

    public function testExportsAndImportsArticles(): void
    {
        $csv = "Name,Code,NetPrice,GrossPrice,VatCode,RevenueTypeCode,CostCenterCode,ArticleId\r\n"
            . "Bike,BIKE,825.00,998.25,VH,8000,,\r\n";
        $http = new ReplayClient(
            ReplayClient::raw(
                200,
                $csv,
                ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=articles.csv'],
            ),
            ReplayClient::raw(200, '[{"row":1}]', ['Content-Type' => 'application/json']),
        );
        $articles = TestTokens::restClient($http)->salesArticles();

        $file = $articles->export();
        $result = $articles->import($file->contents);

        self::assertSame('articles.csv', $file->fileName);
        self::assertSame('text/csv', $file->contentType);
        self::assertStringEndsWith('/sales/articles/export?fileType=csv', (string) $http->requests[0]->getUri());
        self::assertSame([['row' => 1]], $result);

        $upload = $http->requests[1];

        self::assertStringStartsWith('multipart/form-data; boundary=', $upload->getHeaderLine('Content-Type'));
        self::assertStringContainsString('name="file"; filename="articles.csv"', (string) $upload->getBody());
        self::assertStringContainsString($csv, (string) $upload->getBody());
        self::assertStringEndsWith('/sales/articles/import', (string) $upload->getUri());
    }

    public function testExportsXlsx(): void
    {
        $http = new ReplayClient(ReplayClient::raw(200, 'PK', ['Content-Type' => FileType::Xlsx->contentType()]));

        $file = TestTokens::restClient($http)->salesArticles()->export(FileType::Xlsx);

        self::assertSame('articles.xlsx', $file->fileName);
    }

    public function testInputRefusesInvalidValues(): void
    {
        $input = SalesArticleInput::create('Bike', self::VAT_CODE_ID);

        self::assertNull($input->netPrice(null)->netPrice);
        self::assertNull($input->netPrice('1')->grossPrice('2')->netPrice, 'One price at a time.');

        $this->expectException(InvalidArgumentException::class);

        $input->translations(['dutch' => 'Fiets']);
    }

    public function testUpdateNeedsTheIdentity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestTokens::restClient(new ReplayClient())->salesArticles()->update(
            SalesArticleInput::create('Bike', self::VAT_CODE_ID),
        );
    }

    public function testInputRefusesTwoPrices(): void
    {
        self::assertNotNull(SalesArticleInput::create('Bike', self::VAT_CODE_ID)->grossPrice('1')->grossPrice);
        $this->expectException(InvalidArgumentException::class);

        SalesArticleInput::create('', self::VAT_CODE_ID);
    }

    private static function article(): SalesArticle
    {
        return TestTokens::restClient(ReplayClient::jsonFixture('sales-articles-get'))->salesArticles()->get(
            self::ARTICLE_ID,
        );
    }
}
