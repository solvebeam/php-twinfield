<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Currency;

use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A currency of the company with its exchange rates, as Twinfield returns it.
 *
 *   $usd = $twinfield->currencies()->get('USD');
 *   echo $usd->code, ' ', $usd->name, PHP_EOL;
 *   echo $usd->rateOn(new DateTimeImmutable('2026-03-15'))?->rate, PHP_EOL;
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/currencies/
 */
final readonly class Currency
{
    /** @var list<CurrencyRate> Sorted by start date, oldest first. */
    public array $rates;

    /**
     * @param list<CurrencyRate> $rates In any order; at most one rate per start date.
     */
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Currency code, e.g. `USD`. */
        public string $code,
        public string $name,
        /** Short name, e.g. `$`; null when it is not set. */
        public ?string $shortName,
        public CurrencyStatus $status,
        array $rates,
    ) {
        foreach (['code' => $code, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Currency %s must not be empty.', $field));
            }
        }

        usort($rates, static fn (CurrencyRate $a, CurrencyRate $b): int => $a->startDate <=> $b->startDate);

        $startDates = array_map(static fn (CurrencyRate $rate): string => $rate->startDate->format('Y-m-d'), $rates);
        $counts = array_count_values($startDates);
        $duplicates = array_keys(array_filter($counts, static fn (int $count): bool => $count > 1));

        if ($duplicates !== []) {
            throw new InvalidArgumentException(sprintf(
                'Currency %s has more than one rate on %s.',
                $code,
                implode(', ', $duplicates),
            ));
        }

        $this->rates = $rates;
    }

    /**
     * From a `<currency>` element of a save response.
     */
    public static function fromXml(XmlElement $currency, Organisation $organisation): self
    {
        // A currency without rates (such as the base currency) may leave out <rates>.
        $rates = $currency->nullableChild('rates')?->list('rate') ?? [];

        return new self(
            company: new CompanyReference($organisation, $currency->string('office')),
            code: $currency->string('code'),
            name: $currency->string('name'),
            shortName: $currency->nullableString('shortname'),
            status: $currency->enumAttribute('status', CurrencyStatus::class),
            rates: array_map(CurrencyRate::fromXml(...), $rates),
        );
    }

    /**
     * The rate that applies on the date: the one with the latest start date on or before it.
     * Null when the date is before the first rate, or there are no rates.
     */
    public function rateOn(DateTimeInterface $date): ?CurrencyRate
    {
        $day = $date->format('Ymd');
        $found = null;

        foreach ($this->rates as $rate) {
            if ($rate->startDate->format('Ymd') > $day) {
                break;
            }

            $found = $rate;
        }

        return $found;
    }

    /**
     * The rate with the latest start date, or null when there are no rates.
     */
    public function latestRate(): ?CurrencyRate
    {
        return $this->rates === [] ? null : $this->rates[array_key_last($this->rates)];
    }

    /**
     * Input with all values and rates of this currency, to change and pass to update() or save().
     *
     *   $currencies->update($usd->edit()->rate(new DateTimeImmutable('2026-07-01'), '1.0912'));
     */
    public function edit(): CurrencyInput
    {
        $input = CurrencyInput::create($this->code, $this->name)->shortName($this->shortName);

        foreach ($this->rates as $rate) {
            $input = $input->rate($rate->startDate, $rate->rate);
        }

        return $input;
    }
}
