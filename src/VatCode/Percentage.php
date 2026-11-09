<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use InvalidArgumentException;
use SolveBeam\Twinfield\Transaction\Amount;
use Stringable;

/**
 * A percentage, kept as numeric string: no floats.
 *
 *   $percentage = Percentage::of('21');     // 21
 *   $percentage->applyTo('100.00');         // Amount 21.00
 *   echo Percentage::of('9.000000000');     // 9
 *
 * Used for the rate of a VAT code (`21`) and for the share of the VAT that goes to an account
 * (`100`, or `-100` for reverse charge). Twinfield sends rates with nine decimals
 * (`21.000000000`) and shares with one (`100.0`); the value has no trailing zeros, so equal
 * percentages have equal values.
 */
final readonly class Percentage implements Stringable
{
    /**
     * @param numeric-string $value Without trailing zeros, e.g. `21`, `5.5` or `-100`.
     */
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @param Percentage|numeric-string $percentage E.g. `'21'`, `'5.5'` or `'-100'`; at most
     *        nine decimals, with a dot.
     *
     * @throws InvalidArgumentException For anything else, such as `'21%'`, `'5,5'` or `'1e2'`.
     */
    public static function of(self|string $percentage): self
    {
        if ($percentage instanceof self) {
            return $percentage;
        }

        if (preg_match('~^-?\d+(?:\.\d{1,9})?$~', $percentage) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a percentage: use a numeric string with at most 9 decimals, e.g. "21" or "5.5".',
                $percentage,
            ));
        }

        $value = bcadd($percentage, '0', 9);
        $value = rtrim(rtrim($value, '0'), '.');
        $value = $value === '-0' ? '0' : $value;
        assert(is_numeric($value));

        return new self($value);
    }

    /**
     * From external input, such as a file, a form or the command line: any string, validated like of().
     *
     * @throws InvalidArgumentException When it is not a percentage.
     */
    public static function fromString(string $percentage): self
    {
        if (!is_numeric($percentage)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a percentage: use a numeric string with at most 9 decimals, e.g. "21" or "5.5".',
                $percentage,
            ));
        }

        return self::of($percentage);
    }

    /**
     * @param Percentage|numeric-string $percentage
     */
    public function equals(self|string $percentage): bool
    {
        return $this->value === self::of($percentage)->value;
    }

    /**
     * -1, 0 or 1 when this percentage is lower than, equal to or higher than the other.
     *
     * @param Percentage|numeric-string $percentage
     */
    public function compare(self|string $percentage): int
    {
        return bccomp($this->value, self::of($percentage)->value, 9);
    }

    public function isZero(): bool
    {
        return $this->value === '0';
    }

    public function isNegative(): bool
    {
        return str_starts_with($this->value, '-');
    }

    /**
     * This percentage of an amount, rounded half away from zero to cents: `21` of `'10.05'`
     * gives `2.11`.
     *
     * @param Amount|numeric-string $amount
     */
    public function applyTo(Amount|string $amount): Amount
    {
        $exact = bcdiv(bcmul(Amount::of($amount)->value, $this->value, 11), '100', 11);
        $half = str_starts_with($exact, '-') ? '-0.005' : '0.005';

        // bcadd() truncates towards zero, so adding half a cent rounds half away from zero.
        return Amount::of(bcadd($exact, $half, 2));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
