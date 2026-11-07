<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Amount of a transaction line in the transaction, base and reporting currency.
 */
final readonly class LineAmounts
{
    /**
     * @param numeric-string $rate
     * @param ?numeric-string $repRate
     */
    public function __construct(
        /** In the currency of the transaction; always positive, `debitCredit` gives the side. */
        public Amount $value,
        /** In the base currency of the company. */
        public Amount $baseValue,
        /** Exchange rate from the transaction currency to the base currency, e.g. `1` or `1.084500000`. */
        public string $rate,
        /** In the reporting currency; null when the company has none (Twinfield then leaves it out). */
        public ?Amount $repValue,
        public ?string $repRate,
    ) {
    }

    public static function fromXml(XmlElement $line): self
    {
        return new self(
            value: TransactionXml::amount($line, 'value'),
            baseValue: TransactionXml::amount($line, 'basevalue'),
            rate: TransactionXml::optionalRate($line, 'rate')
                ?? throw $line->child('rate')->invalid('an exchange rate'),
            repValue: TransactionXml::optionalAmount($line, 'repvalue'),
            repRate: TransactionXml::optionalRate($line, 'reprate'),
        );
    }
}
