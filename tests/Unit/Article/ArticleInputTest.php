<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Article;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Article\ArticleInput;
use SolveBeam\Twinfield\Article\ArticleType;
use SolveBeam\Twinfield\Article\ArticleXmlWriter;
use SolveBeam\Twinfield\Article\SubArticleInput;
use SolveBeam\Twinfield\Article\SubArticleStatus;
use SolveBeam\Twinfield\Transaction\PerformanceType;

final class ArticleInputTest extends TestCase
{
    public function testIsImmutable(): void
    {
        $input = ArticleInput::normal('BIKE', 'Bike');
        $changed = $input->name('Bicycle')->subArticle(SubArticleInput::create('Bike', '8050'));

        self::assertSame('Bike', $input->name);
        self::assertSame([], $input->subArticles());
        self::assertSame('Bicycle', $changed->name);
        self::assertCount(1, $changed->subArticles());
    }

    public function testEmptyStringsClearOptionalValues(): void
    {
        $input = ArticleInput::normal('BIKE', 'Bike')
            ->shortName('Bike')
            ->unitNames('Piece', 'Pieces')
            ->vatCode('VH')
            ->shortName('')
            ->unitNames('', null)
            ->vatCode('');

        self::assertNull($input->shortName);
        self::assertNull($input->unitNameSingular);
        self::assertNull($input->unitNamePlural);
        self::assertNull($input->vatCode);
    }

    public function testSettingsAreNotSentUnlessSet(): void
    {
        $xml = ArticleXmlWriter::save(
            ArticleInput::normal('BIKE', 'Bike')->subArticle(SubArticleInput::create('Bike', '8050')),
            '1000',
        )->toXml();

        self::assertSame(
            '<article><header><office>1000</office><code>BIKE</code><type>normal</type><name>Bike</name>'
            . '<shortname/><unitnamesingular/><unitnameplural/><vatcode/></header><lines><line>'
            . '<unitspriceexcl/><unitspriceinc/><units>1</units><name>Bike</name><shortname/><subcode/>'
            . '<freetext1>8050</freetext1><freetext2/><freetext3/></line></lines></article>',
            $xml,
        );
    }

    public function testWritesADiscountPercentageWithPerformanceType(): void
    {
        $input = ArticleInput::discount('5DISCOUNT', '5% discount')
            ->percentage()
            ->performanceType(PerformanceType::Services)
            ->allowChangePerformanceType()
            ->subArticle(SubArticleInput::create('5% discount', '8199')->unitPriceExcl('5')->units(2));

        self::assertSame(ArticleType::Discount, $input->type);

        $xml = ArticleXmlWriter::save($input, '1000')->toXml();

        self::assertStringContainsString('<type>discount</type>', $xml);
        self::assertStringContainsString(
            '<performancetype>services</performancetype>'
            . '<allowchangeperformancetype>true</allowchangeperformancetype><percentage>true</percentage>',
            $xml,
        );
        self::assertStringContainsString('<unitspriceexcl>5.00</unitspriceexcl><unitspriceinc/><units>2</units>', $xml);
        self::assertSame(ArticleType::Premium, ArticleInput::premium('FEE', 'Fee')->type);
    }

    public function testAnOrdinaryArticleCannotBeAPercentage(): void
    {
        $this->expectException(LogicException::class);

        ArticleInput::normal('BIKE', 'Bike')->percentage();
    }

    public function testASubArticleWithTheSameCodeIsReplaced(): void
    {
        $input = ArticleInput::normal('BIKE', 'Bike')
            ->subArticle(SubArticleInput::create('Lady', '8050', 'L')->unitPriceExcl('1'))
            ->subArticle(SubArticleInput::create('Man', '8051', 'M'))
            ->subArticle(SubArticleInput::create('Lady', '8050', 'L')->unitPriceExcl('2'));

        self::assertCount(2, $input->subArticles());
        self::assertSame('2.00', $input->subArticles()[0]->unitPriceExcl?->value);
    }

    public function testRemovingAnUnsavedSubArticleLeavesItOut(): void
    {
        $input = ArticleInput::normal('BIKE', 'Bike')
            ->subArticle(SubArticleInput::create('Lady', '8050', 'L'))
            ->subArticle(SubArticleInput::create('Man', '8051', 'M'))
            ->removeSubArticle('M');

        self::assertCount(1, $input->lines());
        self::assertNull($input->lines()[0]['status']);
        self::assertNotSame(SubArticleStatus::Deleted, $input->lines()[0]['status']);
    }

    public function testRemovingAnUnknownSubArticleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Article BIKE has no sub-article without code.');

        ArticleInput::normal('BIKE', 'Bike')->removeSubArticle(null);
    }

    public function testSeveralSubArticlesAllNeedACode(): void
    {
        $input = ArticleInput::normal('BIKE', 'Bike')
            ->subArticle(SubArticleInput::create('Bike', '8050'))
            ->subArticle(SubArticleInput::create('Man', '8051', 'M'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('each of them needs a code');

        $input->lines();
    }

    public function testASubArticleHasOnePrice(): void
    {
        $subArticle = SubArticleInput::create('Bike', '8050')->unitPriceExcl('750')->unitPriceInc('907.5');

        self::assertNull($subArticle->unitPriceExcl);
        self::assertSame('907.50', $subArticle->unitPriceInc?->value);
        self::assertNull($subArticle->unitPriceExcl('750')->unitPriceInc);
    }

    /**
     * @param callable(): mixed $create
     */
    #[DataProvider('invalidInputs')]
    public function testRefusesInvalidValues(callable $create): void
    {
        $this->expectException(InvalidArgumentException::class);

        $create();
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidInputs(): iterable
    {
        $article = static fn (): ArticleInput => ArticleInput::normal('BIKE', 'Bike');
        $subArticle = static fn (): SubArticleInput => SubArticleInput::create('Bike', '8050');

        yield 'empty code' => [static fn () => ArticleInput::normal('', 'Bike')];
        yield 'long code' => [static fn () => ArticleInput::normal(str_repeat('A', 17), 'Bike')];
        yield 'empty name' => [static fn () => ArticleInput::normal('BIKE', '')];
        yield 'long name' => [static fn () => $article()->name(str_repeat('a', 41))];
        yield 'long short name' => [static fn () => $article()->shortName(str_repeat('a', 21))];
        yield 'long unit name' => [static fn () => $article()->unitNames('Piece', str_repeat('a', 21))];
        yield 'invalid VAT code' => [static fn () => $article()->vatCode('v h')];
        yield 'empty sub-article name' => [static fn () => SubArticleInput::create('', '8050')];
        yield 'long sub-article name' => [static fn () => $subArticle()->name(str_repeat('a', 81))];
        yield 'empty sub-article code' => [static fn () => SubArticleInput::create('Bike', '8050', '')];
        yield 'long sub-article code' => [static fn () => SubArticleInput::create('Bike', '8050', str_repeat('A', 17))];
        yield 'no general ledger account' => [static fn () => SubArticleInput::create('Bike', '')];
        yield 'long cost center' => [static fn () => $subArticle()->costCenter(str_repeat('1', 17))];
        yield 'long free text' => [static fn () => $subArticle()->freeText3(str_repeat('a', 41))];
        yield 'zero units' => [static fn () => $subArticle()->units(0)];
        yield 'price with three decimals' => [static fn () => $subArticle()->unitPriceExcl('1.005')];
    }
}
