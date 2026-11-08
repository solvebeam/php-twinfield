<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rate;

use InvalidArgumentException;
use Stringable;

/**
 * Price per unit of a rate, kept as numeric string with four decimals: no floats.
 *
 *   $price = UnitPrice::of('0.15');   // 0.1500
 *   echo $price, PHP_EOL;             // 0.1500
 *
 * Not an Amount: Twinfield keeps rate prices with four decimals (e.g. 0.1550 per kilometre),
 * transaction amounts with two. The value has exactly four decimals, as Twinfield returns it,
 * so equal prices have equal values.
 */
final readonly class UnitPrice implements Stringable
{
    /**
     * @param numeric-string $value Four decimals, e.g. `95.0000`.
     */
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @param UnitPrice|numeric-string $price E.g. `'95'`, `'0.15'` or `'0.1550'`; 0 or more,
     *        at most four decimals, with a dot.
     *
     * @throws InvalidArgumentException For anything else, such as `'0,15'`, `'1e3'`, `'-1'` or `'0.12345'`.
     */
    public static function of(self|string $price): self
    {
        if ($price instanceof self) {
            return $price;
        }

        if (preg_match('~^\d+(?:\.\d{1,4})?$~', $price) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a unit price: use a numeric string of 0 or more with at most 4 decimals, e.g. "0.15".',
                $price,
            ));
        }

        return new self(bcadd($price, '0', 4));
    }

    /**
     * From external input, such as a file, a form or the command line: any string, validated like of().
     *
     * @throws InvalidArgumentException When it is not a unit price.
     */
    public static function fromString(string $price): self
    {
        if (!is_numeric($price)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a unit price: use a numeric string of 0 or more with at most 4 decimals, e.g. "0.15".',
                $price,
            ));
        }

        return self::of($price);
    }

    /**
     * @param UnitPrice|numeric-string $price
     */
    public function equals(self|string $price): bool
    {
        return $this->value === self::of($price)->value;
    }

    public function isZero(): bool
    {
        return bccomp($this->value, '0', 4) === 0;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
