<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Currency;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A currency to create or update. Immutable: every method returns a new input.
 *
 *   $input = CurrencyInput::create('USD', 'US dollar')
 *       ->shortName('$')
 *       ->rate(new DateTimeImmutable('2026-01-01'), '1.0850')
 *       ->rate(new DateTimeImmutable('2026-07-01'), '1.0912');
 *
 * For an existing currency, start from `$currency->edit()`: it has all current rates, so
 * the update sends them again and only the rates you change or remove differ.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/currencies/
 */
final readonly class CurrencyInput
{
    /**
     * @param array<int, ExchangeRate> $rates Rates to save, by start date as int `yyyymmdd`.
     * @param array<int, ExchangeRate|null> $removedRates Rates to delete, by start date as int
     *        `yyyymmdd`; with the rate when it is known.
     */
    private function __construct(
        public string $code,
        public string $name,
        public ?string $shortName,
        private array $rates,
        private array $removedRates,
    ) {
        XmlValue::code($code);

        if ($name === '') {
            throw new InvalidArgumentException('Currency name must not be empty.');
        }
    }

    /**
     * @param string $code Currency code: A-Z, 0-9, `_`, `-` or `+`, e.g. `USD`.
     */
    public static function create(string $code, string $name): self
    {
        return new self($code, $name, null, [], []);
    }

    public function name(string $name): self
    {
        return new self($this->code, $name, $this->shortName, $this->rates, $this->removedRates);
    }

    /**
     * Short name, e.g. `$`. Null or an empty string clears it.
     */
    public function shortName(?string $shortName): self
    {
        $shortName = $shortName === '' ? null : $shortName;

        return new self($this->code, $this->name, $shortName, $this->rates, $this->removedRates);
    }

    /**
     * Adds the rate as of the start date, or replaces the rate with the same start date.
     *
     * @param ExchangeRate|numeric-string $rate E.g. `'1.0850'`.
     */
    public function rate(DateTimeInterface $startDate, ExchangeRate|string $rate): self
    {
        $key = (int) XmlValue::date($startDate);
        $rates = $this->rates;
        $removedRates = $this->removedRates;

        $rates[$key] = ExchangeRate::of($rate);
        unset($removedRates[$key]);

        return new self($this->code, $this->name, $this->shortName, $rates, $removedRates);
    }

    /**
     * Deletes the rate with this start date when the currency is saved.
     */
    public function removeRate(DateTimeInterface $startDate): self
    {
        $key = (int) XmlValue::date($startDate);
        $rates = $this->rates;
        $removedRates = $this->removedRates;

        $removedRates[$key] = $rates[$key] ?? null;
        unset($rates[$key]);

        return new self($this->code, $this->name, $this->shortName, $rates, $removedRates);
    }

    /**
     * Rates to save, oldest first.
     *
     * @return list<CurrencyRate>
     */
    public function rates(): array
    {
        $rates = [];

        foreach ($this->sorted($this->rates) as $key => $rate) {
            $rates[] = new CurrencyRate(XmlValue::toDate((string) $key), $rate);
        }

        return $rates;
    }

    /**
     * Start dates of the rates to delete, oldest first.
     *
     * @return list<DateTimeImmutable>
     */
    public function removedRates(): array
    {
        return array_map(
            static fn (int $key): DateTimeImmutable => XmlValue::toDate((string) $key),
            array_keys($this->sorted($this->removedRates)),
        );
    }

    /**
     * The `<currency>` request to save this currency in a company.
     */
    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('currency');
        $currency = $xml->root();

        $xml->add($currency, 'office', $company);
        $xml->add($currency, 'code', $this->code);
        $xml->add($currency, 'name', $this->name);
        $xml->add($currency, 'shortname', $this->shortName);

        if ($this->rates === [] && $this->removedRates === []) {
            return $xml;
        }

        $rates = $xml->add($currency, 'rates');

        foreach ($this->sorted($this->rates) as $startDate => $rate) {
            $element = $xml->add($rates, 'rate');
            $xml->add($element, 'startdate', (string) $startDate);
            $xml->add($element, 'rate', $rate->value);
        }

        foreach ($this->sorted($this->removedRates) as $startDate => $rate) {
            $element = $xml->add($rates, 'rate', null, ['status' => 'deleted']);
            $xml->add($element, 'startdate', (string) $startDate);

            if ($rate !== null) {
                $xml->add($element, 'rate', $rate->value);
            }
        }

        return $xml;
    }

    /**
     * @template T
     *
     * @param array<int, T> $rates
     *
     * @return array<int, T>
     */
    private function sorted(array $rates): array
    {
        ksort($rates);

        return $rates;
    }
}
