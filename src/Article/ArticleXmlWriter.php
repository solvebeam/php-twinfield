<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

use DOMElement;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * Writes the `<article>` requests of the XML web services.
 *
 * @internal
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/articles/#createupdatedelete
 */
final class ArticleXmlWriter
{
    /**
     * The request to read an article.
     */
    public static function read(string $company, string $code): XmlBuilder
    {
        $xml = XmlBuilder::create('read');
        $xml->add($xml->root(), 'type', 'article');
        $xml->add($xml->root(), 'office', $company);
        $xml->add($xml->root(), 'code', $code);

        return $xml;
    }

    /**
     * The request to create or update an article with all values of the input.
     *
     * @throws LogicException Without sub-articles, or when one of several sub-articles has no code.
     */
    public static function save(ArticleInput $article, string $company): XmlBuilder
    {
        // First: an input that cannot be saved fails before anything is built.
        $subArticles = $article->lines();

        $xml = XmlBuilder::create('article');
        $header = $xml->add($xml->root(), 'header');

        $xml->add($header, 'office', $company);
        $xml->add($header, 'code', $article->code);
        $xml->add($header, 'type', $article->type->value);
        $xml->add($header, 'name', $article->name);
        // Empty elements clear the value, as Twinfield returns them.
        $xml->add($header, 'shortname', $article->shortName);
        $xml->add($header, 'unitnamesingular', $article->unitNameSingular);
        $xml->add($header, 'unitnameplural', $article->unitNamePlural);
        $xml->add($header, 'vatcode', $article->vatCode);

        // The settings are only sent when set, so Twinfield keeps its defaults for the others.
        self::addBool($xml, $header, 'allowchangevatcode', $article->allowChangeVatCode);

        if ($article->performanceType !== null) {
            $xml->add($header, 'performancetype', $article->performanceType->value);
        }

        self::addBool($xml, $header, 'allowchangeperformancetype', $article->allowChangePerformanceType);
        self::addBool($xml, $header, 'percentage', $article->percentage);
        self::addBool($xml, $header, 'allowdiscountorpremium', $article->allowDiscountOrPremium);
        self::addBool($xml, $header, 'allowchangeunitsprice', $article->allowChangeUnitPrice);
        self::addBool($xml, $header, 'allowdecimalquantity', $article->allowDecimalQuantity);

        $lines = $xml->add($xml->root(), 'lines');

        foreach ($subArticles as ['input' => $subArticle, 'status' => $status]) {
            $line = $xml->add($lines, 'line', null, $status === null ? [] : ['status' => $status->value]);

            $xml->add($line, 'unitspriceexcl', $subArticle->unitPriceExcl?->value);
            $xml->add($line, 'unitspriceinc', $subArticle->unitPriceInc?->value);
            $xml->add($line, 'units', (string) $subArticle->units);
            $xml->add($line, 'name', $subArticle->name);
            $xml->add($line, 'shortname', $subArticle->shortName);
            $xml->add($line, 'subcode', $subArticle->code);
            $xml->add($line, 'freetext1', $subArticle->generalLedgerAccount);
            $xml->add($line, 'freetext2', $subArticle->costCenter);
            $xml->add($line, 'freetext3', $subArticle->freeText3);
        }

        return $xml;
    }

    /**
     * The request to delete (`Deleted`) or activate (`Active`) an article.
     */
    public static function changeStatus(string $company, string $code, ArticleStatus $status): XmlBuilder
    {
        $xml = XmlBuilder::create('article');
        $header = $xml->add($xml->root(), 'header', null, ['status' => $status->value]);
        $xml->add($header, 'office', $company);
        $xml->add($header, 'code', $code);

        return $xml;
    }

    private static function addBool(XmlBuilder $xml, DOMElement $parent, string $name, ?bool $value): void
    {
        if ($value !== null) {
            $xml->add($parent, $name, XmlValue::boolean($value));
        }
    }
}
