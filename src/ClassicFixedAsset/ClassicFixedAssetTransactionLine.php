<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DimensionReference;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Transaction\TransactionXml;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A transaction line of a fixed asset (`translines/transline`): the purchase, and later for
 * example a sale or conversion, on the investment account with the asset as dimension 3.
 *
 *   foreach ($asset->transactionLines as $line) {
 *       echo $line->transactionType, ' ', $line->number, '/', $line->line, ': ', $line->amount, PHP_EOL;
 *   }
 *
 * Twinfield links these lines itself when you book a transaction with the asset code as
 * dimension 3; they cannot be sent with the asset.
 */
final readonly class ClassicFixedAssetTransactionLine
{
    /**
     * @param array<int, DimensionReference> $dimensions Dimension 1 to 6 by position; positions
     *     without a dimension are left out.
     */
    public function __construct(
        /** Transaction type (journal) code, e.g. `MEMO` or `INK`. */
        public string $transactionType,
        /** Name of the transaction type, e.g. `Memorial`; null when Twinfield gives none. */
        public ?string $transactionTypeName,
        /** Transaction number, e.g. `202600012`. */
        public int $number,
        /** Line number in the transaction. */
        public int $line,
        /** Amount of the line; negative for credit. */
        public Amount $amount,
        public Period $period,
        public array $dimensions,
        /** As Twinfield returns it in `@inuse`. */
        public bool $inUse,
        /** As Twinfield returns it in `@checked`. */
        public bool $checked,
        /** True when the line cannot be changed. */
        public bool $locked,
    ) {
        if ($transactionType === '') {
            throw new InvalidArgumentException('Transaction type must not be empty.');
        }

        if ($number < 1 || $line < 1) {
            throw new InvalidArgumentException(sprintf(
                'Transaction number and line must be 1 or more, %d/%d given.',
                $number,
                $line,
            ));
        }

        foreach (array_keys($dimensions) as $position) {
            if ($position < 1 || $position > 6) {
                throw new InvalidArgumentException(sprintf('Dimension position must be 1 to 6, %d given.', $position));
            }
        }
    }

    /**
     * From a `<transline>` element.
     *
     * @internal
     */
    public static function fromXml(XmlElement $line): self
    {
        $type = $line->child('code');
        $dimensions = [];

        for ($position = 1; $position <= 6; $position++) {
            $dimension = $line->child('dim' . $position);

            // Positions without a dimension are empty elements, e.g. <dim4 name="" dimensiontype=""/>.
            if ($dimension->text() === '') {
                continue;
            }

            $dimensionType = $dimension->attribute('dimensiontype');
            $name = $dimension->attribute('name');

            $dimensions[$position] = new DimensionReference(
                $dimension->text(),
                $dimensionType === '' ? null : $dimensionType,
                $name === '' ? null : $name,
            );
        }

        $typeName = $type->attribute('name');

        return new self(
            transactionType: $line->string('code'),
            transactionTypeName: $typeName === '' ? null : $typeName,
            number: self::positive($line, 'number'),
            line: self::positive($line, 'line'),
            amount: TransactionXml::amount($line, 'amount'),
            period: TransactionXml::period($line, 'period'),
            dimensions: $dimensions,
            inUse: $line->boolAttribute('inuse'),
            checked: $line->boolAttribute('checked'),
            locked: $line->boolAttribute('locked'),
        );
    }

    /**
     * Dimension 1 to 6, or null when the line has none at that position.
     */
    public function dimension(int $position): ?DimensionReference
    {
        if ($position < 1 || $position > 6) {
            throw new InvalidArgumentException(sprintf('Dimension position must be 1 to 6, %d given.', $position));
        }

        return $this->dimensions[$position] ?? null;
    }

    /**
     * The general ledger account (dimension 1), e.g. the investment account `0200`.
     */
    public function account(): ?DimensionReference
    {
        return $this->dimension(1);
    }

    /**
     * The cost center (dimension 2), when the line has one.
     */
    public function costCenter(): ?DimensionReference
    {
        return $this->dimension(2);
    }

    private static function positive(XmlElement $line, string $name): int
    {
        $value = $line->int($name);

        if ($value < 1) {
            throw new UnexpectedResponseException(
                sprintf('%s/%s is %d, expected 1 or more.', $line->path, $name, $value),
            );
        }

        return $value;
    }
}
