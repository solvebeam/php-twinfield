<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\PerformanceType;

/**
 * Reads the `<article>` responses of the XML web services.
 *
 * @internal
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/articles/
 */
final class ArticleXmlReader
{
    public static function article(XmlElement $article, CompanyReference $company): Article
    {
        $header = $article->child('header');

        // Twinfield leaves the performance type elements out for discounts and premiums.
        $hasPerformanceType = $header->nullableChild('performancetype') !== null;
        $hasAllowChangePerformanceType = $header->nullableChild('allowchangeperformancetype') !== null;

        return new Article(
            company: $company,
            code: $header->string('code'),
            status: self::status($header),
            type: $header->enum('type', ArticleType::class),
            name: $header->string('name'),
            shortName: $header->nullableString('shortname'),
            unitNameSingular: $header->nullableString('unitnamesingular'),
            unitNamePlural: $header->nullableString('unitnameplural'),
            vatCode: $header->nullableString('vatcode'),
            allowChangeVatCode: $header->bool('allowchangevatcode'),
            performanceType: $hasPerformanceType
                ? $header->nullableEnum('performancetype', PerformanceType::class)
                : null,
            allowChangePerformanceType: $hasAllowChangePerformanceType
                ? $header->bool('allowchangeperformancetype')
                : null,
            percentage: $header->bool('percentage'),
            allowDiscountOrPremium: $header->bool('allowdiscountorpremium'),
            allowChangeUnitPrice: $header->bool('allowchangeunitsprice'),
            allowDecimalQuantity: $header->bool('allowdecimalquantity'),
            subArticles: array_map(self::subArticle(...), $article->child('lines')->list('line')),
        );
    }

    /**
     * The status of the article in a response: `article/header/@status`.
     */
    public static function status(XmlElement $header): ArticleStatus
    {
        return $header->enumAttribute('status', ArticleStatus::class);
    }

    private static function subArticle(XmlElement $line): SubArticle
    {
        $generalLedgerAccount = $line->child('freetext1');
        $costCenter = $line->child('freetext2');

        return new SubArticle(
            code: $line->nullableString('subcode'),
            name: $line->string('name'),
            shortName: $line->nullableString('shortname'),
            units: $line->int('units'),
            unitPriceExcl: self::amount($line->child('unitspriceexcl')),
            unitPriceInc: self::amount($line->child('unitspriceinc')),
            generalLedgerAccount: $line->string('freetext1'),
            generalLedgerAccountName: self::name($generalLedgerAccount),
            costCenter: $line->nullableString('freetext2'),
            costCenterName: $costCenter->text() === '' ? null : self::name($costCenter),
            freeText3: $line->nullableString('freetext3'),
            status: $line->enumAttribute('status', SubArticleStatus::class),
            inUse: $line->boolAttribute('inuse'),
        );
    }

    private static function amount(XmlElement $element): Amount
    {
        try {
            return Amount::fromString($element->text());
        } catch (InvalidArgumentException) {
            throw $element->invalid('an amount with at most 2 decimals');
        }
    }

    /**
     * The `name` attribute of a dimension element; an empty name is null.
     */
    private static function name(XmlElement $element): ?string
    {
        $name = $element->attribute('name');

        return $name === '' ? null : $name;
    }
}
