<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A sub-article to create or update, as part of an ArticleInput. Immutable: every method returns a
 * new input.
 *
 *   SubArticleInput::create("Lady's bike", '8050', 'BIKE.L')->unitPriceExcl('750.00');
 *
 * Set the price excluding or including VAT; Twinfield calculates the other with the VAT code.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/articles/#sub-articles
 */
final readonly class SubArticleInput
{
    private function __construct(
        /** Null for the only sub-article of an article, which may have no code. */
        public ?string $code,
        public string $name,
        public ?string $shortName,
        public int $units,
        public ?Amount $unitPriceExcl,
        public ?Amount $unitPriceInc,
        public string $generalLedgerAccount,
        public ?string $costCenter,
        public ?string $freeText3,
    ) {
        // Sub-article codes are not limited to A-Z, 0-9, _, - and +: the documentation has `BIKE.L`.
        // So only the length is checked.
        if ($code !== null && ($code === '' || mb_strlen($code) > 16)) {
            throw new InvalidArgumentException(sprintf(
                'Sub-article code must be 1 to 16 characters, "%s" given.',
                $code,
            ));
        }

        if ($name === '') {
            throw new InvalidArgumentException('Sub-article name must not be empty.');
        }

        XmlValue::maxLength('sub-article name', $name, 80);

        if ($shortName !== null) {
            XmlValue::maxLength('sub-article short name', $shortName, 20);
        }

        if ($units < 1) {
            throw new InvalidArgumentException(sprintf('Sub-article units must be 1 or more, %d given.', $units));
        }

        if ($generalLedgerAccount === '') {
            throw new InvalidArgumentException('Sub-article general ledger account must not be empty.');
        }

        XmlValue::maxLength('sub-article general ledger account', $generalLedgerAccount, 16);

        if ($costCenter !== null) {
            XmlValue::maxLength('sub-article cost center', $costCenter, 16);
        }

        if ($freeText3 !== null) {
            XmlValue::maxLength('sub-article free text 3', $freeText3, 40);
        }
    }

    /**
     * @param string  $generalLedgerAccount Code of the general ledger account for the revenue.
     * @param ?string $code                 Code of the sub-article; null only for an article with one
     *                                      sub-article.
     */
    public static function create(string $name, string $generalLedgerAccount, ?string $code = null): self
    {
        return new self($code, $name, null, 1, null, null, $generalLedgerAccount, null, null);
    }

    /**
     * The sub-article as Twinfield returned it, with both prices. `SubArticle::edit()` uses this.
     */
    public static function existing(SubArticle $subArticle): self
    {
        return new self(
            $subArticle->code,
            $subArticle->name,
            $subArticle->shortName,
            $subArticle->units,
            $subArticle->unitPriceExcl,
            $subArticle->unitPriceInc,
            $subArticle->generalLedgerAccount,
            $subArticle->costCenter,
            $subArticle->freeText3,
        );
    }

    public function name(string $name): self
    {
        return $this->copy(name: $name);
    }

    /**
     * @param ?string $shortName At most 20 characters; null clears it.
     */
    public function shortName(?string $shortName): self
    {
        return $this->copy(shortName: $shortName === '' ? null : $shortName);
    }

    /**
     * The number of units of the article per quantity (default 1).
     */
    public function units(int $units): self
    {
        return $this->copy(units: $units);
    }

    /**
     * @param Amount|numeric-string $price Price excluding VAT, e.g. `'750.00'`; clears the price
     *                                     including VAT.
     */
    public function unitPriceExcl(Amount|string $price): self
    {
        return $this->copy(unitPriceExcl: Amount::of($price), unitPriceInc: null);
    }

    /**
     * @param Amount|numeric-string $price Price including VAT, e.g. `'907.50'`; clears the price
     *                                     excluding VAT.
     */
    public function unitPriceInc(Amount|string $price): self
    {
        return $this->copy(unitPriceExcl: null, unitPriceInc: Amount::of($price));
    }

    public function generalLedgerAccount(string $code): self
    {
        return $this->copy(generalLedgerAccount: $code);
    }

    public function costCenter(?string $code): self
    {
        return $this->copy(costCenter: $code === '' ? null : $code);
    }

    /**
     * @param ?string $text At most 40 characters; null clears it.
     */
    public function freeText3(?string $text): self
    {
        return $this->copy(freeText3: $text === '' ? null : $text);
    }

    private function copy(
        ?string $name = null,
        string|false|null $shortName = false,
        ?int $units = null,
        Amount|false|null $unitPriceExcl = false,
        Amount|false|null $unitPriceInc = false,
        ?string $generalLedgerAccount = null,
        string|false|null $costCenter = false,
        string|false|null $freeText3 = false,
    ): self {
        return new self(
            $this->code,
            $name ?? $this->name,
            $shortName === false ? $this->shortName : $shortName,
            $units ?? $this->units,
            $unitPriceExcl === false ? $this->unitPriceExcl : $unitPriceExcl,
            $unitPriceInc === false ? $this->unitPriceInc : $unitPriceInc,
            $generalLedgerAccount ?? $this->generalLedgerAccount,
            $costCenter === false ? $this->costCenter : $costCenter,
            $freeText3 === false ? $this->freeText3 : $freeText3,
        );
    }
}
