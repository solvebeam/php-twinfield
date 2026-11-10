<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\Performance;
use SolveBeam\Twinfield\VatCode\VatCode;

/**
 * A sales invoice line to create or update. Immutable: every method returns a new input.
 *
 *   // An article: Twinfield takes the price, VAT code and account from the article.
 *   SalesInvoiceLineInput::article('510', subarticle: '501.01')->quantity('2');
 *
 *   // The one-off item `0`: your own description and price.
 *   SalesInvoiceLineInput::oneOff('Consultancy November')->unitPriceExcl('950.00')->vatCode('VH');
 *
 * Twinfield calculates the amounts and the VAT of the line.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/
 */
final readonly class SalesInvoiceLineInput
{
    /**
     * @param numeric-string $quantity
     * @param array{1: ?string, 2: ?string, 3: ?string} $freeTexts
     */
    private function __construct(
        public string $article,
        public ?string $subarticle,
        /** Line id of an existing line; null for a new line, which gets an id when sent. */
        public ?int $id,
        public string $quantity,
        public ?int $units,
        public ?Amount $unitPriceExcl,
        public ?Amount $unitPriceInc,
        public ?string $vatCode,
        public ?bool $allowDiscountOrPremium,
        public ?string $description,
        public ?Performance $performance,
        public array $freeTexts,
        /** `dim1`: general ledger account. */
        public ?string $dim1,
    ) {
        // Article codes are not limited to A-Z, 0-9, _, - and +: the documentation has sub-article
        // `501.01`. So only the length is checked.
        foreach (['article' => $article, 'subarticle' => $subarticle] as $field => $code) {
            if ($code !== null && ($code === '' || mb_strlen($code) > 16)) {
                throw new InvalidArgumentException(sprintf(
                    'Sales invoice line %s must be 1 to 16 characters, "%s" given.',
                    $field,
                    $code,
                ));
            }
        }

        if ($id !== null && $id < 1) {
            throw new InvalidArgumentException(sprintf('Sales invoice line id must be 1 or more, %d given.', $id));
        }

        if ($units !== null && $units < 1) {
            throw new InvalidArgumentException(
                sprintf('Sales invoice line units must be 1 or more, %d given.', $units),
            );
        }

        if ($unitPriceExcl !== null && $unitPriceInc !== null) {
            throw new InvalidArgumentException(
                'A sales invoice line has a unit price excluding or including VAT, not both: '
                . 'the invoice type decides which.',
            );
        }

        if ($vatCode !== null) {
            XmlValue::vatCode($vatCode);
        }

        if ($description !== null) {
            if (!self::hasOwnDescription($article)) {
                throw new InvalidArgumentException(sprintf(
                    'A description is only allowed for the one-off item 0 or -, not for article %s: '
                    . 'Twinfield takes the name of the article.',
                    $article,
                ));
            }

            XmlValue::maxLength('sales invoice line description', $description, 110);
        }

        if ($performance !== null && ($performance->country !== null || $performance->vatNumber !== null)) {
            throw new InvalidArgumentException(
                'A sales invoice line only has a performance type and date: '
                . 'Twinfield takes the country and VAT number of the customer.',
            );
        }

        foreach ($freeTexts as $number => $freeText) {
            if ($freeText !== null) {
                XmlValue::maxLength(sprintf('sales invoice line free text %d', $number), $freeText, 36);
            }
        }

        if ($dim1 !== null) {
            XmlValue::code($dim1);
        }
    }

    /**
     * A line with an article (item) of the company. Without a price, Twinfield takes the price
     * of the article.
     *
     * @param string $article Article code, e.g. `510`.
     */
    public static function article(string $article, ?string $subarticle = null): self
    {
        return new self(
            article: $article,
            subarticle: $subarticle,
            id: null,
            quantity: '1',
            units: null,
            unitPriceExcl: null,
            unitPriceInc: null,
            vatCode: null,
            allowDiscountOrPremium: null,
            description: null,
            performance: null,
            freeTexts: [1 => null, 2 => null, 3 => null],
            dim1: null,
        );
    }

    /**
     * A line with the one-off item `0`, which is in every company: your own description, and a
     * price with unitPriceExcl() or unitPriceInc(). Twinfield books it on the account of the
     * invoice type, or on dim1() when the invoice type allows that.
     *
     * @param string $description At most 110 characters.
     */
    public static function oneOff(string $description): self
    {
        return self::article('0')->description($description);
    }

    public function subarticle(?string $subarticle): self
    {
        return $this->with(['subarticle' => $subarticle === '' ? null : $subarticle]);
    }

    /**
     * Line id of an existing line, so an update changes it. `edit()` sets it.
     */
    public function id(?int $id): self
    {
        return $this->with(['id' => $id]);
    }

    /**
     * Quantity, e.g. `'2'` or `'1.5'`; `'1'` by default. Negative for a credit line.
     *
     * @param numeric-string|int $quantity
     */
    public function quantity(string|int $quantity): self
    {
        $quantity = (string) $quantity;

        // Checked at runtime too: the value often comes from external input.
        if (preg_match('~^-?\d+(?:\.\d+)?$~', $quantity) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a quantity, e.g. "2" or "1.5".', $quantity));
        }

        return $this->with(['quantity' => $quantity]);
    }

    /**
     * Number of units per quantity. Not sent when not set.
     */
    public function units(?int $units): self
    {
        return $this->with(['units' => $units]);
    }

    /**
     * Price per unit without VAT, for an invoice type with prices excluding VAT. Only when the
     * price of the article may be changed; otherwise Twinfield takes the price of the article.
     *
     * @param Amount|numeric-string $price
     */
    public function unitPriceExcl(Amount|string $price): self
    {
        return $this->with(['unitPriceExcl' => Amount::of($price), 'unitPriceInc' => null]);
    }

    /**
     * Price per unit with VAT, for an invoice type with prices including VAT. Only when the
     * price of the article may be changed; otherwise Twinfield takes the price of the article.
     *
     * @param Amount|numeric-string $price
     */
    public function unitPriceInc(Amount|string $price): self
    {
        return $this->with(['unitPriceExcl' => null, 'unitPriceInc' => Amount::of($price)]);
    }

    /**
     * VAT code, e.g. `'VH'` or `$twinfield->vatCodes()->get('VH')`. Without it, Twinfield
     * takes the VAT code of the article.
     */
    public function vatCode(string|VatCode|null $vatCode): self
    {
        return $this->with(['vatCode' => $vatCode instanceof VatCode ? $vatCode->code : $vatCode]);
    }

    /**
     * Whether the discount or premium of the invoice applies to this line.
     */
    public function allowDiscountOrPremium(?bool $allow = true): self
    {
        return $this->with(['allowDiscountOrPremium' => $allow]);
    }

    /**
     * Only for the one-off item `0` and the text line `-`; at most 110 characters.
     */
    public function description(?string $description): self
    {
        return $this->with(['description' => $description === '' ? null : $description]);
    }

    /**
     * Performance type and date for an ICT VAT code, when set up on the invoice lines, e.g.
     * `Performance::services(new DateTimeImmutable('2026-11-03'))`.
     */
    public function performance(?Performance $performance): self
    {
        return $this->with(['performance' => $performance]);
    }

    /**
     * Free text field 1, 2 or 3 of the invoice type; at most 36 characters. Null or an empty
     * string clears it.
     */
    public function freeText(int $number, ?string $value): self
    {
        if ($number < 1 || $number > 3) {
            throw new InvalidArgumentException(sprintf('Free text field must be 1, 2 or 3, %d given.', $number));
        }

        $freeTexts = $this->freeTexts;
        $freeTexts[$number] = $value === '' ? null : $value;

        return $this->with(['freeTexts' => $freeTexts]);
    }

    /**
     * `dim1`: general ledger account for the one-off item, instead of the account of the
     * invoice type. Only when "One-off items > Can adjust general ledger account" is ticked on
     * the invoice type.
     */
    public function dim1(?string $dim1): self
    {
        return $this->with(['dim1' => $dim1 === '' ? null : $dim1]);
    }

    /**
     * Adds the `<line>` to `<lines>`.
     *
     * @internal
     */
    public function appendTo(XmlBuilder $xml, DOMElement $lines, int $id): void
    {
        $line = $xml->add($lines, 'line', attributes: ['id' => (string) $id]);

        $xml->add($line, 'article', $this->article);

        if ($this->subarticle !== null) {
            $xml->add($line, 'subarticle', $this->subarticle);
        }

        $xml->add($line, 'quantity', $this->quantity);

        if ($this->units !== null) {
            $xml->add($line, 'units', (string) $this->units);
        }

        if ($this->unitPriceExcl !== null) {
            $xml->add($line, 'unitspriceexcl', $this->unitPriceExcl->value);
        }

        if ($this->unitPriceInc !== null) {
            $xml->add($line, 'unitspriceinc', $this->unitPriceInc->value);
        }

        if ($this->vatCode !== null) {
            $xml->add($line, 'vatcode', $this->vatCode);
        }

        if ($this->allowDiscountOrPremium !== null) {
            $xml->add($line, 'allowdiscountorpremium', XmlValue::boolean($this->allowDiscountOrPremium));
        }

        if ($this->description !== null) {
            $xml->add($line, 'description', $this->description);
        }

        if ($this->performance !== null) {
            if ($this->performance->date !== null) {
                $xml->add($line, 'performancedate', XmlValue::date($this->performance->date));
            }

            $xml->add($line, 'performancetype', $this->performance->type->value);
        }

        foreach ($this->freeTexts as $number => $freeText) {
            if ($freeText !== null) {
                $xml->add($line, 'freetext' . $number, $freeText);
            }
        }

        if ($this->dim1 !== null) {
            $xml->add($line, 'dim1', $this->dim1);
        }
    }

    /**
     * A quantity as Twinfield returns and accepts it: digits with an optional sign and decimals.
     *
     * @internal
     *
     * @phpstan-assert-if-true numeric-string $quantity
     */
    public static function isQuantity(string $quantity): bool
    {
        return preg_match('~^-?\d+(?:\.\d+)?$~', $quantity) === 1;
    }

    /**
     * True for the one-off item `0` and the text line `-`: only these have their own description.
     *
     * @internal
     */
    public static function hasOwnDescription(string $article): bool
    {
        return $article === '0' || $article === '-';
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
