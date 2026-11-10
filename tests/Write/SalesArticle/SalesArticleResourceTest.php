<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\SalesArticle;

use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Rest\ValidationException;
use SolveBeam\Twinfield\Rest\VersionConflictException;
use SolveBeam\Twinfield\SalesArticle\SalesArticleInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\VatCode\VatCodeType;

/**
 * Creates, updates and deletes a temporary sales article (REST API); always deletes it again.
 *
 * Not run live yet: needs `composer test:write` and the scope writeArticles.
 */
final class SalesArticleResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $this->requireScopes(Scope::WriteArticles, Scope::ReadVat);

        $articles = $this->twinfield->salesArticles();
        $vatCode = $this->twinfield->vatCodes()->searchDetails()->type(VatCodeType::Sales)->limit(1)->get()->items[0];
        $code = 'SDKTEST' . random_int(1000, 9999);
        $id = null;

        try {
            $created = $articles->create(
                SalesArticleInput::create('SDK test article', $vatCode->id)->code($code)->netPrice('10.5')
                    ->translations(['en-GB' => 'SDK test article']),
            );
            $id = $created->id;

            self::assertSame($code, $created->code);
            self::assertSame('10.5000', (string) $created->netPrice);
            self::assertSame(['en-GB' => 'SDK test article'], $created->translations);
            self::assertEquals($created, $articles->get($id));

            try {
                $articles->create(SalesArticleInput::create('Duplicate', $vatCode->id)->code($code));
                self::fail('Expected a ValidationException for a code in use.');
            } catch (ValidationException $exception) {
                self::assertSame(422, $exception->status);
            }

            $updated = $articles->update($created->edit()->name('SDK test article (updated)')->grossPrice('12.10'));

            self::assertSame('SDK test article (updated)', $updated->name);
            self::assertGreaterThan($created->version, $updated->version);

            try {
                $articles->update($created->edit()->name('Outdated'));
                self::fail('Expected a VersionConflictException for an outdated version.');
            } catch (VersionConflictException) {
                self::assertSame('SDK test article (updated)', $articles->get($id)->name);
            }

            $articles->delete($id, $updated->version);
            $id = null;

            $this->expectException(NotFoundException::class);
            $articles->get($updated->id);
        } finally {
            if ($id !== null) {
                $articles->delete($id);
            }
        }
    }
}
