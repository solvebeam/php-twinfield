<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A payment discount of a customer or supplier: a percentage off when paid within a number
 * of days, e.g. 2% within 8 days.
 *
 *   PaymentCondition::of(8, '2');
 */
final readonly class PaymentCondition
{
    /**
     * @param numeric-string $discountPercentage E.g. `2` or `2.5`.
     */
    private function __construct(
        public int $discountDays,
        public string $discountPercentage,
    ) {
    }

    /**
     * @param numeric-string $discountPercentage E.g. `'2'` or `'2.5'`: 0–100, with a dot.
     *
     * @throws InvalidArgumentException For negative days or an invalid percentage.
     */
    public static function of(int $discountDays, string $discountPercentage): self
    {
        if ($discountDays < 0) {
            throw new InvalidArgumentException(sprintf('Discount days must be 0 or more, %d given.', $discountDays));
        }

        if (preg_match('~^\d+(?:\.\d+)?$~', $discountPercentage) !== 1 || bccomp($discountPercentage, '100', 10) > 0) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a discount percentage: use a numeric string from 0 to 100 with a dot, e.g. "2.5".',
                $discountPercentage,
            ));
        }

        return new self($discountDays, $discountPercentage);
    }

    /**
     * From a `<paymentcondition>` element of a dimension.
     */
    public static function fromXml(XmlElement $condition): self
    {
        $percentage = $condition->child('discountpercentage');

        if (!is_numeric($percentage->text())) {
            throw $percentage->invalid('a percentage from 0 to 100');
        }

        try {
            return self::of($condition->int('discountdays'), $percentage->text());
        } catch (InvalidArgumentException) {
            throw $percentage->invalid('a percentage from 0 to 100');
        }
    }
}
