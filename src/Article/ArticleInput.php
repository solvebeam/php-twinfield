<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\PerformanceType;

/**
 * An article to create or update. Immutable: every method returns a new input.
 *
 *   $input = ArticleInput::normal('BIKE', 'Bike summer edition')
 *       ->unitNames('Piece', 'Pieces')
 *       ->vatCode('VH')
 *       ->subArticle(SubArticleInput::create("Lady's bike", '8050', 'BIKE.L')->unitPriceExcl('750.00'))
 *       ->subArticle(SubArticleInput::create("Man's bike", '8051', 'BIKE.M')->unitPriceExcl('750.00'));
 *
 * An article needs at least one sub-article. Settings you do not set (the `allow…` methods) are
 * not sent, so Twinfield uses its defaults. For an existing article, start from `$article->edit()`,
 * which has all its values and sub-articles.
 *
 * @phpstan-type SubArticleEntry array{input: SubArticleInput, status: ?SubArticleStatus, saved: bool}
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/articles/
 */
final readonly class ArticleInput
{
    /**
     * @param array<string, SubArticleEntry> $entries Sub-articles by code (`''` for no code).
     */
    private function __construct(
        public string $code,
        public ArticleType $type,
        public string $name,
        public ?string $shortName,
        public ?string $unitNameSingular,
        public ?string $unitNamePlural,
        public ?string $vatCode,
        public ?bool $allowChangeVatCode,
        public ?PerformanceType $performanceType,
        public ?bool $allowChangePerformanceType,
        public ?bool $percentage,
        public ?bool $allowDiscountOrPremium,
        public ?bool $allowChangeUnitPrice,
        public ?bool $allowDecimalQuantity,
        private array $entries,
    ) {
        // Article codes are not limited to A-Z, 0-9, _, - and +, so only the length is checked.
        if ($code === '' || mb_strlen($code) > 16) {
            throw new InvalidArgumentException(sprintf('Article code must be 1 to 16 characters, "%s" given.', $code));
        }

        if ($name === '') {
            throw new InvalidArgumentException('Article name must not be empty.');
        }

        XmlValue::maxLength('article name', $name, 40);

        foreach (
            [
                'article short name' => $shortName,
                'article unit name (singular)' => $unitNameSingular,
                'article unit name (plural)' => $unitNamePlural,
            ] as $field => $value
        ) {
            if ($value !== null) {
                XmlValue::maxLength($field, $value, 20);
            }
        }

        if ($vatCode !== null) {
            XmlValue::vatCode($vatCode);
        }

        if ($percentage === true && $type === ArticleType::Normal) {
            throw new LogicException(sprintf(
                'Article %s: only a discount or premium can be a percentage.',
                $code,
            ));
        }
    }

    /**
     * An ordinary article.
     */
    public static function normal(string $code, string $name): self
    {
        return self::ofType(ArticleType::Normal, $code, $name);
    }

    /**
     * A deduction. Its price is an amount, or a percentage with `percentage()`.
     */
    public static function discount(string $code, string $name): self
    {
        return self::ofType(ArticleType::Discount, $code, $name);
    }

    /**
     * A premium. Its price is an amount, or a percentage with `percentage()`.
     */
    public static function premium(string $code, string $name): self
    {
        return self::ofType(ArticleType::Premium, $code, $name);
    }

    /**
     * The article as Twinfield returned it, with all its sub-articles. `Article::edit()` uses this.
     */
    public static function existing(Article $article): self
    {
        $entries = [];

        foreach ($article->subArticles as $subArticle) {
            $entries[$subArticle->code ?? ''] = [
                'input' => SubArticleInput::existing($subArticle),
                'status' => $subArticle->status === SubArticleStatus::Deleted ? SubArticleStatus::Deleted : null,
                'saved' => true,
            ];
        }

        return new self(
            $article->code,
            $article->type,
            $article->name,
            $article->shortName,
            $article->unitNameSingular,
            $article->unitNamePlural,
            $article->vatCode,
            $article->allowChangeVatCode,
            $article->performanceType,
            $article->allowChangePerformanceType,
            $article->percentage,
            $article->allowDiscountOrPremium,
            $article->allowChangeUnitPrice,
            $article->allowDecimalQuantity,
            $entries,
        );
    }

    /**
     * @param string $name At most 40 characters.
     */
    public function name(string $name): self
    {
        return $this->with(['name' => $name]);
    }

    /**
     * @param ?string $shortName At most 20 characters; null clears it.
     */
    public function shortName(?string $shortName): self
    {
        return $this->with(['shortName' => $shortName === '' ? null : $shortName]);
    }

    /**
     * Names of the unit, e.g. `unitNames('Piece', 'Pieces')`; at most 20 characters each, null clears.
     */
    public function unitNames(?string $singular, ?string $plural): self
    {
        return $this->with([
            'unitNameSingular' => $singular === '' ? null : $singular,
            'unitNamePlural' => $plural === '' ? null : $plural,
        ]);
    }

    /**
     * Default VAT code, e.g. `VH`; null clears it.
     */
    public function vatCode(?string $vatCode): self
    {
        return $this->with(['vatCode' => $vatCode === '' ? null : $vatCode]);
    }

    /**
     * Whether the VAT code may be changed on the invoice line.
     */
    public function allowChangeVatCode(bool $allow = true): self
    {
        return $this->with(['allowChangeVatCode' => $allow]);
    }

    /**
     * Performance type for ICT VAT codes; null clears it.
     */
    public function performanceType(?PerformanceType $type): self
    {
        return $this->with(['performanceType' => $type]);
    }

    /**
     * Whether the performance type may be changed on the invoice line.
     */
    public function allowChangePerformanceType(bool $allow = true): self
    {
        return $this->with(['allowChangePerformanceType' => $allow]);
    }

    /**
     * The price of this discount or premium is a percentage instead of an amount.
     *
     * @throws LogicException For an ordinary article.
     */
    public function percentage(bool $percentage = true): self
    {
        return $this->with(['percentage' => $percentage]);
    }

    /**
     * Whether a discount or premium may be applied to this article.
     */
    public function allowDiscountOrPremium(bool $allow = true): self
    {
        return $this->with(['allowDiscountOrPremium' => $allow]);
    }

    /**
     * Whether the unit price may be changed on the invoice line.
     */
    public function allowChangeUnitPrice(bool $allow = true): self
    {
        return $this->with(['allowChangeUnitPrice' => $allow]);
    }

    /**
     * Whether the quantity on the invoice line may have decimals.
     */
    public function allowDecimalQuantity(bool $allow = true): self
    {
        return $this->with(['allowDecimalQuantity' => $allow]);
    }

    /**
     * Adds a sub-article, or replaces the sub-article with the same code. A deleted sub-article
     * with this code is recovered.
     */
    public function subArticle(SubArticleInput $subArticle): self
    {
        $key = $subArticle->code ?? '';
        $entry = $this->entries[$key] ?? null;
        $entries = $this->entries;

        $entries[$key] = [
            'input' => $subArticle,
            // Twinfield recovers a deleted sub-article with the status active.
            'status' => $entry !== null && $entry['status'] === SubArticleStatus::Deleted
                ? SubArticleStatus::Active
                : $entry['status'] ?? null,
            'saved' => $entry['saved'] ?? false,
        ];

        return $this->with(['entries' => $entries]);
    }

    /**
     * Deletes the sub-article with this code when the article is saved. Pass null for the
     * sub-article without code.
     *
     * @throws InvalidArgumentException When the input has no sub-article with this code.
     */
    public function removeSubArticle(?string $code): self
    {
        $key = $code ?? '';
        $entry = $this->entries[$key] ?? throw new InvalidArgumentException(sprintf(
            'Article %s has no sub-article %s.',
            $this->code,
            $code ?? 'without code',
        ));

        $entries = $this->entries;

        // A sub-article that is not saved yet: leaving it out is enough.
        if ($entry['saved']) {
            $entries[$key] = [...$entry, 'status' => SubArticleStatus::Deleted];
        } else {
            unset($entries[$key]);
        }

        return $this->with(['entries' => $entries]);
    }

    /**
     * The sub-articles that are saved with the article, without the ones to delete.
     *
     * @return list<SubArticleInput>
     */
    public function subArticles(): array
    {
        return array_values(array_map(
            static fn (array $entry): SubArticleInput => $entry['input'],
            array_filter(
                $this->entries,
                static fn (array $entry): bool => $entry['status'] !== SubArticleStatus::Deleted,
            ),
        ));
    }

    /**
     * All sub-article lines to send: the status is null for an ordinary line, `Deleted` for a
     * sub-article to delete and `Active` for a deleted sub-article to recover.
     *
     * @return list<array{input: SubArticleInput, status: ?SubArticleStatus}>
     *
     * @throws LogicException Without sub-articles, or when one of several sub-articles has no code.
     */
    public function lines(): array
    {
        $kept = $this->subArticles();

        if ($kept === []) {
            throw new LogicException(sprintf(
                'Article %s needs at least one sub-article: add one with subArticle().',
                $this->code,
            ));
        }

        if (count($kept) > 1) {
            foreach ($kept as $subArticle) {
                if ($subArticle->code === null) {
                    throw new LogicException(sprintf(
                        'Article %s has several sub-articles: each of them needs a code.',
                        $this->code,
                    ));
                }
            }
        }

        return array_values(array_map(
            static fn (array $entry): array => ['input' => $entry['input'], 'status' => $entry['status']],
            $this->entries,
        ));
    }

    private static function ofType(ArticleType $type, string $code, string $name): self
    {
        return new self($code, $type, $name, null, null, null, null, null, null, null, null, null, null, null, []);
    }

    /**
     * @param array<string, mixed> $changes Property name => new value.
     */
    private function with(array $changes): self
    {
        $values = [...get_object_vars($this), ...$changes];

        return new self(
            $values['code'],
            $values['type'],
            $values['name'],
            $values['shortName'],
            $values['unitNameSingular'],
            $values['unitNamePlural'],
            $values['vatCode'],
            $values['allowChangeVatCode'],
            $values['performanceType'],
            $values['allowChangePerformanceType'],
            $values['percentage'],
            $values['allowDiscountOrPremium'],
            $values['allowChangeUnitPrice'],
            $values['allowDecimalQuantity'],
            $values['entries'],
        );
    }
}
