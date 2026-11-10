<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

use InvalidArgumentException;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * The difference of a matched line that is written off, given as a discount, or booked as an
 * exchange rate difference:
 *
 *   MatchInput::customers($date)
 *       ->line('KAS', 202600015, 2)
 *       ->line('VRK', 202600071, 1, writeOff: WriteOff::discount('2.00'));
 *
 * Use a negative amount on a credit line, as Twinfield asks: "Include an "-" on credit lines."
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/matching/
 */
final readonly class WriteOff
{
    public Amount $amount;

    /**
     * @param Amount|numeric-string $amount E.g. `'2.00'`; not zero.
     */
    public function __construct(
        Amount|string $amount,
        public WriteOffType $type,
    ) {
        $this->amount = Amount::of($amount);

        if ($this->amount->isZero()) {
            throw new InvalidArgumentException('A write-off amount must not be zero.');
        }
    }

    /**
     * @param Amount|numeric-string $amount
     */
    public static function discount(Amount|string $amount): self
    {
        return new self($amount, WriteOffType::Discount);
    }

    /**
     * @param Amount|numeric-string $amount
     */
    public static function writeOff(Amount|string $amount): self
    {
        return new self($amount, WriteOffType::WriteOff);
    }

    /**
     * @param Amount|numeric-string $amount
     */
    public static function currency(Amount|string $amount): self
    {
        return new self($amount, WriteOffType::Currency);
    }
}
