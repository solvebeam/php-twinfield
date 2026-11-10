<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesArticle;

use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rate\UnitPrice;
use SolveBeam\Twinfield\Rest\FileType;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Rest\RawBody;
use SolveBeam\Twinfield\Rest\RestFile;
use SolveBeam\Twinfield\Rest\RestResponse;
use SolveBeam\Twinfield\Rest\ValidationException;
use SolveBeam\Twinfield\Rest\VersionConflictException;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Sales articles of the company (REST API, Sales Articles): search, read, create, update and delete,
 * import and export as CSV or XLSX. Scope `readArticles`, and `writeArticles` to change them.
 *
 * These are not the articles of the Classic invoicing module: a line of `salesInvoices()` refers to
 * an article and sub-article of `articles()` (XML web services), which is a separate list.
 *
 *   $articles = $twinfield->company('1000')->salesArticles();
 *
 *   foreach ($articles->search('Bike*')->all() as $article) {
 *       echo $article->code, ' ', $article->name, ' ', $article->netPrice, PHP_EOL;
 *   }
 *
 *   $created = $articles->create(SalesArticleInput::create('Bike', $vatCodeId)->code('BIKE')->netPrice('825'));
 *   $updated = $articles->update($created->edit()->netPrice('799'));
 *   $articles->delete($updated->id, $updated->version);
 *
 * `find()` gives null instead of a NotFoundException, and `save()` creates or updates depending on
 * whether the input has an ID.
 *
 * @see https://api.accounting.twinfield.com/Api/swagger/ui/index#!/Sales32Articles
 */
final readonly class SalesArticleResource
{
    private const string PATH = '/organisations/{organisationId}/companies/{companyId}/sales/articles';

    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    public function search(string $pattern = '*'): SalesArticleSearch
    {
        $search = new SalesArticleSearch($this->twinfield, self::PATH);

        return $pattern === '*' ? $search : $search->pattern($pattern);
    }

    /**
     * @throws NotFoundException When the company has no article with this GUID.
     */
    public function get(string $id): SalesArticle
    {
        return self::read($this->twinfield->rest('GET', self::PATH . '/' . Guid::of($id))->json(), $this->company());
    }

    /**
     * The article, or null when the company has no article with this GUID.
     */
    public function find(string $id): ?SalesArticle
    {
        try {
            return $this->get($id);
        } catch (NotFoundException) {
            return null;
        }
    }

    /**
     * @throws ValidationException When Twinfield refuses the article, e.g. a code that is already in use
     *                             (HTTP 422).
     */
    public function create(SalesArticleInput $article): SalesArticle
    {
        if ($article->id !== null) {
            throw new InvalidArgumentException('The article already has an ID: use update().');
        }

        return $this->savedArticle($this->twinfield->rest('POST', self::PATH, body: self::toJson($article)));
    }

    /**
     * Saves all fields of the input. Twinfield checks the version (`If-Match`): when the article changed
     * since you read it, it throws a VersionConflictException.
     *
     * @throws VersionConflictException When the article changed since you read it (HTTP 412).
     * @throws ValidationException      When Twinfield refuses the article.
     */
    public function update(SalesArticleInput $article): SalesArticle
    {
        if ($article->id === null || $article->version === null) {
            throw new InvalidArgumentException(
                'Update needs the ID and version of the article: start from $article->edit().',
            );
        }

        $response = $this->twinfield->rest(
            'PUT',
            self::PATH . '/' . $article->id,
            body: self::toJson($article),
            headers: ['If-Match' => (string) $article->version],
        );

        return $this->savedArticle($response, $article->id);
    }

    /**
     * Creates the article when the input has no ID, and updates it otherwise (an input from
     * `$article->edit()`).
     *
     * @throws VersionConflictException When the article changed since you read it (HTTP 412).
     * @throws ValidationException      When Twinfield refuses the article.
     */
    public function save(SalesArticleInput $article): SalesArticle
    {
        return $article->id === null ? $this->create($article) : $this->update($article);
    }

    /**
     * @param ?int $version The version you read; null deletes whatever the version (`If-Match: *`).
     *
     * @throws VersionConflictException When the article changed since you read it.
     * @throws NotFoundException        When the company has no article with this GUID.
     */
    public function delete(string $id, ?int $version = null): void
    {
        $this->twinfield->rest(
            'DELETE',
            self::PATH . '/' . Guid::of($id),
            headers: ['If-Match' => $version === null ? '*' : (string) $version],
        );
    }

    /**
     * The cultures articles can have a name in, for `SalesArticleInput::translations()`.
     *
     * @return list<Culture>
     */
    public function cultures(): array
    {
        $path = '/organisations/{organisationId}/companies/{companyId}/sales/articlecultures';

        return array_map(
            static fn (JsonValue $culture): Culture
                => new Culture($culture->string('code'), $culture->string('localisedName')),
            $this->twinfield->rest('GET', $path)->jsonList(),
        );
    }

    /**
     * All articles as a CSV or XLSX file, with the columns of the import.
     */
    public function export(FileType $fileType = FileType::Csv): RestFile
    {
        $response = $this->twinfield->rest('GET', self::PATH . '/export', ['fileType' => $fileType->value]);

        return RestFile::fromResponse($response, 'articles.' . $fileType->value);
    }

    /**
     * Creates or updates articles from a CSV or XLSX file with the columns of export(): `Name`, `Code`,
     * `NetPrice`, `GrossPrice`, `VatCode`, `RevenueTypeCode`, `CostCenterCode` and `ArticleId` (empty for
     * a new article).
     *
     * @return list<array<array-key, mixed>> The result per row, as Twinfield returns it.
     *
     * @throws ValidationException When Twinfield refuses the file.
     */
    public function import(string $contents, FileType $fileType = FileType::Csv): array
    {
        $response = $this->twinfield->rest(
            'POST',
            self::PATH . '/import',
            body: RawBody::multipart('file', $contents, 'articles.' . $fileType->value, $fileType->contentType()),
        );

        return array_map(static fn (JsonValue $row): array => $row->toArray(), $response->jsonList());
    }

    /**
     * @internal Also used by SalesArticleSearch.
     */
    public static function read(JsonValue $json, CompanyReference $company): SalesArticle
    {
        return new SalesArticle(
            company: $company,
            id: $json->uuid('id'),
            name: $json->string('name'),
            code: $json->nullableString('code'),
            netPrice: UnitPrice::of($json->decimal('netPrice')),
            grossPrice: UnitPrice::of($json->decimal('grossPrice')),
            vatCodeId: $json->uuid('vatId'),
            revenueTypeId: $json->uuid('revenueTypeId'),
            costCenterId: $json->nullableUuid('costCenterId'),
            translations: $json->stringMap('translations'),
            version: $json->int('version'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function toJson(SalesArticleInput $article): array
    {
        $json = [
            'name' => $article->name,
            'code' => $article->code ?? '',
            'vatId' => $article->vatCodeId,
            'translations' => $article->translations === [] ? (object) [] : $article->translations,
        ];

        // Leaving out a price lets Twinfield calculate it from the other one and the VAT code.
        if ($article->netPrice !== null) {
            $json['netPrice'] = $article->netPrice;
        }

        if ($article->grossPrice !== null) {
            $json['grossPrice'] = $article->grossPrice;
        }

        if ($article->revenueTypeId !== null) {
            $json['revenueTypeId'] = $article->revenueTypeId;
        }

        if ($article->costCenterId !== null) {
            $json['costCenterId'] = $article->costCenterId;
        }

        if ($article->id !== null) {
            $json['id'] = $article->id;
            $json['version'] = $article->version;
        }

        return $json;
    }

    /**
     * The saved article from the response; when Twinfield sends no body, read it by its ID (the `Id`
     * header for a created article).
     */
    private function savedArticle(RestResponse $response, ?string $id = null): SalesArticle
    {
        if (trim($response->body()) !== '') {
            return self::read($response->json(), $this->company());
        }

        $id ??= $response->header('Id');

        if ($id === '' || !Guid::isValid($id)) {
            throw new UnexpectedResponseException(sprintf(
                'Twinfield saved the article (HTTP %d) but sent no article and no Id header.',
                $response->status(),
            ));
        }

        return $this->get($id);
    }

    private function company(): CompanyReference
    {
        return $this->twinfield->companyReference();
    }
}
