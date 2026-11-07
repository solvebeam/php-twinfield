<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Currency;

use InvalidArgumentException;
use Stringable;

/**
 * Exchange rate of a currency, kept as numeric string: no floats, any number of decimals.
 *
 *   $rate = ExchangeRate::of('1.0850');   // 1.085
 *   echo $rate, PHP_EOL;                  // 1.085
 *
 * Not an Amount: a rate is not money and has more than two decimals. The value is normalised
 * (no leading or trailing zeros), so equal rates have equal values.
 */
final readonly class ExchangeRate implements Stringable
{
    /**
     * @param numeric-string $value Normalised, e.g. `0.8282` or `12`.
     */
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @param ExchangeRate|numeric-string $rate E.g. `'1.0850'` or `'0.8282'`; positive, with a dot.
     *
     * @throws InvalidArgumentException For anything else, such as `'1,08'`, `'1e3'`, `'0'` or `'-1'`.
     */
    public static function of(self|string $rate): self
    {
        if ($rate instanceof self) {
            return $rate;
        }

        if (preg_match('~^(\d+)(?:\.(\d+))?$~', $rate, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not an exchange rate: use a positive numeric string with a dot, e.g. "1.0850".',
                $rate,
            ));
        }

        $integer = ltrim($matches[1], '0');
        $fraction = rtrim($matches[2] ?? '', '0');
        $value = ($integer === '' ? '0' : $integer) . ($fraction === '' ? '' : '.' . $fraction);

        if ($value === '0' || !is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Exchange rate must be more than 0, "%s" given.', $rate));
        }

        return new self($value);
    }

    /**
     * From external input, such as a file, a form or the command line: any string, validated like of().
     *
     * @throws InvalidArgumentException When it is not a positive exchange rate.
     */
    public static function fromString(string $rate): self
    {
        if (!is_numeric($rate)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not an exchange rate: use a positive numeric string with a dot, e.g. "1.0850".',
                $rate,
            ));
        }

        return self::of($rate);
    }

    /**
     * @param ExchangeRate|numeric-string $rate
     */
    public function equals(self|string $rate): bool
    {
        return $this->value === self::of($rate)->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
