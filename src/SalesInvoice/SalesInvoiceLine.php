<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DimensionReference;
use SolveBeam\Twinfield\Transaction\Performance;
use SolveBeam\Twinfield\Transaction\TransactionXml;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A line of a sales invoice as Twinfield returns it: an article with its quantity, price and
 * the amounts Twinfield calculated.
 *
 *   foreach ($invoice->lines as $line) {
 *       echo $line->quantity, ' x ', $line->description, ': ', $line->valueInc, PHP_EOL;
 *   }
 */
final readonly class SalesInvoiceLine
{
    public function __construct(
        public int $id,
        /** Article (item) code; `0` is the one-off item. */
        public string $article,
        /** Sub-article code. */
        public string $subarticle,
        /** @var numeric-string E.g. `1` or `2.00`, as Twinfield returns it. */
        public string $quantity,
        /** Number of units per quantity. */
        public int $units,
        /** Price per unit without VAT; null for an invoice type with prices including VAT. */
        public ?Amount $unitPriceExcl,
        /** Price per unit with VAT; null for an invoice type with prices excluding VAT. */
        public ?Amount $unitPriceInc,
        /** VAT code, e.g. `VH`. */
        public string $vatCode,
        public bool $allowDiscountOrPremium,
        /** The text you entered for a one-off item, otherwise the name of the article. */
        public string $description,
        /** Only for ICT VAT codes; null when not set up on the invoice type. */
        public ?Performance $performance,
        /** Free text fields of the invoice type; null when empty. */
        public ?string $freeText1,
        public ?string $freeText2,
        public ?string $freeText3,
        /** `dim1`: the general ledger account Twinfield books the line on. */
        public DimensionReference $dim1,
        public Amount $valueExcl,
        public Amount $vatValue,
        public Amount $valueInc,
    ) {
        if (($unitPriceExcl === null) === ($unitPriceInc === null)) {
            throw new InvalidArgumentException(sprintf(
                'Sales invoice line %d must have a unit price excluding or including VAT, not both or none.',
                $id,
            ));
        }

        foreach (['article' => $article, 'subarticle' => $subarticle, 'vatCode' => $vatCode] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Sales invoice line %d %s must not be empty.', $id, $field));
            }
        }
    }

    /**
     * From a `<line>` element of a read, save or calculate response.
     *
     * @internal
     */
    public static function fromXml(XmlElement $line): self
    {
        $quantity = $line->child('quantity');

        if (!SalesInvoiceLineInput::isQuantity($quantity->text())) {
            throw $quantity->invalid('a quantity');
        }

        try {
            return new self(
                id: $line->intAttribute('id'),
                article: $line->string('article'),
                subarticle: $line->string('subarticle'),
                quantity: $quantity->text(),
                units: $line->int('units'),
                // Twinfield returns the price of the kind the invoice type uses, and leaves out the other.
                unitPriceExcl: TransactionXml::optionalAmount($line, 'unitspriceexcl'),
                unitPriceInc: TransactionXml::optionalAmount($line, 'unitspriceinc'),
                vatCode: $line->string('vatcode'),
                allowDiscountOrPremium: $line->bool('allowdiscountorpremium'),
                description: $line->string('description'),
                performance: TransactionXml::performance($line),
                // Free text fields are empty when not filled in.
                freeText1: $line->nullableString('freetext1'),
                freeText2: $line->nullableString('freetext2'),
                freeText3: $line->nullableString('freetext3'),
                dim1: TransactionXml::dimension($line, 'dim1'),
                valueExcl: TransactionXml::amount($line, 'valueexcl'),
                vatValue: TransactionXml::amount($line, 'vatvalue'),
                valueInc: TransactionXml::amount($line, 'valueinc'),
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s: %s', $line->path, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    public function isOneOff(): bool
    {
        return $this->article === '0';
    }

    /**
     * Input with the values of this line, to change and pass back with the invoice. Keeps the
     * line id, so an update changes this line.
     *
     * The description and the general ledger account are only sent for the one-off item:
     * Twinfield takes them from the article otherwise.
     */
    public function edit(): SalesInvoiceLineInput
    {
        $input = SalesInvoiceLineInput::article($this->article, $this->subarticle)
            ->id($this->id)
            ->quantity($this->quantity)
            ->units($this->units)
            ->vatCode($this->vatCode)
            ->allowDiscountOrPremium($this->allowDiscountOrPremium)
            ->freeText(1, $this->freeText1)
            ->freeText(2, $this->freeText2)
            ->freeText(3, $this->freeText3);

        if ($this->unitPriceExcl !== null) {
            $input = $input->unitPriceExcl($this->unitPriceExcl);
        }

        if ($this->unitPriceInc !== null) {
            $input = $input->unitPriceInc($this->unitPriceInc);
        }

        if ($this->performance !== null) {
            $input = $input->performance($this->performance);
        }

        if (SalesInvoiceLineInput::hasOwnDescription($this->article)) {
            $input = $input->description($this->description);
        }

        if ($this->isOneOff()) {
            $input = $input->dim1($this->dim1->code);
        }

        return $input;
    }
}
