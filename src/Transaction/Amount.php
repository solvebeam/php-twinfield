<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use InvalidArgumentException;
use Stringable;

/**
 * Money amount with two decimals, kept as numeric string: no floats, no cents.
 *
 *   $amount = Amount::of('121');            // 121.00
 *   $total = $amount->plus('21.5');         // 142.50
 *   echo $total, PHP_EOL;                   // 142.50
 *
 * Calculations use bcmath, so they are exact. The currency is not part of the amount:
 * it is the currency of the transaction.
 */
final readonly class Amount implements Stringable
{
    /**
     * @param numeric-string $value Normalised: two decimals, `0.00` instead of `-0.00`.
     */
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @param Amount|numeric-string $amount E.g. `'121'`, `'121.5'` or `'-12.10'`; at most two decimals.
     *
     * @throws InvalidArgumentException For anything else, such as `'1,50'`, `'1e3'` or `'0.125'`.
     */
    public static function of(self|string $amount): self
    {
        if ($amount instanceof self) {
            return $amount;
        }

        if (preg_match('~^-?\d+(?:\.\d{1,2})?$~', $amount) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not an amount: use a numeric string with at most 2 decimals, e.g. "121.00".',
                $amount,
            ));
        }

        return self::normalise(bcadd($amount, '0', 2));
    }

    /**
     * From external input, such as a file, a form or the command line: any string, validated like of().
     *
     *   $amount = Amount::fromString($row['amount']);
     *
     * @throws InvalidArgumentException When it is not an amount with at most 2 decimals.
     */
    public static function fromString(string $amount): self
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not an amount: use a numeric string with at most 2 decimals, e.g. "121.00".',
                $amount,
            ));
        }

        return self::of($amount);
    }

    /**
     * Like of(), but null stays null.
     *
     * @param Amount|numeric-string|null $amount
     */
    public static function ofNullable(self|string|null $amount): ?self
    {
        return $amount === null ? null : self::of($amount);
    }

    public static function zero(): self
    {
        return new self('0.00');
    }

    /**
     * @param Amount|numeric-string $amount
     */
    public function plus(self|string $amount): self
    {
        return self::normalise(bcadd($this->value, self::of($amount)->value, 2));
    }

    /**
     * @param Amount|numeric-string $amount
     */
    public function minus(self|string $amount): self
    {
        return self::normalise(bcsub($this->value, self::of($amount)->value, 2));
    }

    public function negate(): self
    {
        return self::normalise(bcsub('0', $this->value, 2));
    }

    /**
     * Without sign.
     */
    public function absolute(): self
    {
        return $this->isNegative() ? $this->negate() : $this;
    }

    public function isZero(): bool
    {
        return bccomp($this->value, '0', 2) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->value, '0', 2) < 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->value, '0', 2) > 0;
    }

    /**
     * @param Amount|numeric-string $amount
     */
    public function equals(self|string $amount): bool
    {
        return $this->compare($amount) === 0;
    }

    /**
     * -1, 0 or 1, like the spaceship operator.
     *
     * @param Amount|numeric-string $amount
     */
    public function compare(self|string $amount): int
    {
        return bccomp($this->value, self::of($amount)->value, 2);
    }

    /**
     * Sum of amounts; zero for none.
     *
     * @param iterable<Amount|numeric-string> $amounts
     */
    public static function sum(iterable $amounts): self
    {
        $sum = self::zero();

        foreach ($amounts as $amount) {
            $sum = $sum->plus($amount);
        }

        return $sum;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function normalise(string $value): self
    {
        assert(is_numeric($value));

        return new self($value === '-0.00' ? '0.00' : $value);
    }
}
