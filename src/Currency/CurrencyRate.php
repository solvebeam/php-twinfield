<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Currency;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Exchange rate of a currency as of a start date, as Twinfield returns it.
 * The rate applies until the start date of the next rate.
 */
final readonly class CurrencyRate
{
    public function __construct(
        /** First day the rate applies; the time is always 00:00. */
        public DateTimeImmutable $startDate,
        public ExchangeRate $rate,
    ) {
    }

    /**
     * From a `<rate>` element of `<currency><rates>`.
     *
     * @internal
     */
    public static function fromXml(XmlElement $rate): self
    {
        $startDate = $rate->nullableDate('startdate')
            ?? throw new UnexpectedResponseException(sprintf('%s/startdate is empty.', $rate->path));

        try {
            $value = ExchangeRate::fromString($rate->string('rate'));
        } catch (InvalidArgumentException) {
            throw $rate->child('rate')->invalid('a positive exchange rate, e.g. 1.0850');
        }

        return new self($startDate, $value);
    }
}
