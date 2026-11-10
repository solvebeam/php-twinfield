<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesArticle;

use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Rate\UnitPrice;

/**
 * A sales article to create or update. Immutable: every method returns a new input.
 *
 *   $input = SalesArticleInput::create('Bike', $vatCodeId)->code('BIKE')->netPrice('825.00');
 *
 * Set the net or the gross price; Twinfield calculates the other with the VAT code. For an existing
 * article, start from `$article->edit()`, which keeps its version for the optimistic lock.
 */
final readonly class SalesArticleInput
{
    /**
     * @param array<string, string> $translations
     */
    private function __construct(
        public ?string $id,
        public ?int $version,
        public string $name,
        public ?string $code,
        public ?UnitPrice $netPrice,
        public ?UnitPrice $grossPrice,
        public string $vatCodeId,
        public ?string $revenueTypeId,
        public ?string $costCenterId,
        public array $translations,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Sales article name must not be empty.');
        }

        if ($netPrice !== null && $grossPrice !== null) {
            throw new LogicException('Set the net price or the gross price of a sales article, not both.');
        }

        Guid::of($vatCodeId, 'VAT code ID');

        foreach ([$revenueTypeId, $costCenterId] as $id) {
            if ($id !== null) {
                Guid::of($id);
            }
        }

        foreach ($translations as $culture => $translation) {
            if (preg_match('~^[a-z]{2}-[A-Z]{2}$~', $culture) !== 1 || $translation === '') {
                throw new InvalidArgumentException(sprintf(
                    'Translation "%s" needs a culture code such as nl-NL and a name.',
                    $culture,
                ));
            }
        }
    }

    /**
     * @param string $vatCodeId GUID of the VAT code, from `vatCodes()->searchDetails()`.
     */
    public static function create(string $name, string $vatCodeId): self
    {
        return new self(null, null, $name, null, null, null, Guid::of($vatCodeId, 'VAT code ID'), null, null, []);
    }

    /**
     * The article to update: its GUID and the version you read.
     */
    public function withIdentity(string $id, int $version): self
    {
        return $this->copy(id: Guid::of($id), version: $version);
    }

    public function name(string $name): self
    {
        return $this->copy(name: $name);
    }

    public function code(?string $code): self
    {
        return $this->copy(code: $code === '' ? null : $code);
    }

    /**
     * @param UnitPrice|numeric-string|null $price Price without VAT, e.g. `'825.00'`; clears the gross price.
     */
    public function netPrice(UnitPrice|string|null $price): self
    {
        return $this->copy(netPrice: $price === null ? null : UnitPrice::of($price), grossPrice: null);
    }

    /**
     * @param UnitPrice|numeric-string|null $price Price with VAT, e.g. `'998.25'`; clears the net price.
     */
    public function grossPrice(UnitPrice|string|null $price): self
    {
        return $this->copy(netPrice: null, grossPrice: $price === null ? null : UnitPrice::of($price));
    }

    public function vatCode(string $vatCodeId): self
    {
        return $this->copy(vatCodeId: Guid::of($vatCodeId, 'VAT code ID'));
    }

    /**
     * @param ?string $revenueTypeId GUID; null for the default of the company settings.
     */
    public function revenueType(?string $revenueTypeId): self
    {
        return $this->copy(revenueTypeId: $revenueTypeId === null ? null : Guid::of($revenueTypeId));
    }

    public function costCenter(?string $costCenterId): self
    {
        return $this->copy(costCenterId: $costCenterId === null ? null : Guid::of($costCenterId));
    }

    /**
     * @param array<string, string> $translations Name per culture, e.g. `['en-GB' => 'Bike']`; see
     *                                            `salesArticles()->cultures()`.
     */
    public function translations(array $translations): self
    {
        return $this->copy(translations: $translations);
    }

    /**
     * @param array<string, string>|null $translations
     */
    private function copy(
        ?string $id = null,
        ?int $version = null,
        ?string $name = null,
        string|false|null $code = false,
        UnitPrice|false|null $netPrice = false,
        UnitPrice|false|null $grossPrice = false,
        ?string $vatCodeId = null,
        string|false|null $revenueTypeId = false,
        string|false|null $costCenterId = false,
        ?array $translations = null,
    ): self {
        return new self(
            $id ?? $this->id,
            $version ?? $this->version,
            $name ?? $this->name,
            $code === false ? $this->code : $code,
            $netPrice === false ? $this->netPrice : $netPrice,
            $grossPrice === false ? $this->grossPrice : $grossPrice,
            $vatCodeId ?? $this->vatCodeId,
            $revenueTypeId === false ? $this->revenueTypeId : $revenueTypeId,
            $costCenterId === false ? $this->costCenterId : $costCenterId,
            $translations ?? $this->translations,
        );
    }
}
