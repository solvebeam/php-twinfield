<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A percentage of a VAT code from a date on, to create or update. Immutable: every method
 * returns a new input.
 *
 *   VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21')
 *       ->name('BTW 21%')
 *       ->shortName('VH 21%')
 *       ->account(VatAccountInput::vat('1530')->group('NL', 'NL1A'));
 *
 * Without name, the line gets the name of the VAT code when it is saved.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/vat/
 */
final readonly class VatPercentageInput
{
    /**
     * @param list<VatAccountInput> $accounts
     */
    private function __construct(
        /** First day the percentage applies; the time is always 00:00. */
        public DateTimeImmutable $from,
        /** The VAT rate, e.g. `21`. */
        public Percentage $percentage,
        /** Null: the name of the VAT code. */
        public ?string $name,
        public ?string $shortName,
        public array $accounts,
    ) {
        self::validateRate($percentage);

        if ($name !== null) {
            XmlValue::maxLength('VAT percentage name', $name, 40);
        }

        if ($shortName !== null) {
            XmlValue::maxLength('VAT percentage short name', $shortName, 20);
        }
    }

    /**
     * @param DateTimeInterface $from First day the percentage applies.
     * @param Percentage|numeric-string $percentage The VAT rate, e.g. `'21'` or `'5.5'`.
     */
    public static function from(DateTimeInterface $from, Percentage|string $percentage): self
    {
        return new self(XmlValue::toDate(XmlValue::date($from)), Percentage::of($percentage), null, null, []);
    }

    /**
     * At most 40 characters. Null or an empty string: the name of the VAT code.
     */
    public function name(?string $name): self
    {
        return new self($this->from, $this->percentage, $name === '' ? null : $name, $this->shortName, $this->accounts);
    }

    /**
     * At most 20 characters. Null or an empty string clears it.
     */
    public function shortName(?string $shortName): self
    {
        return new self(
            $this->from,
            $this->percentage,
            $this->name,
            $shortName === '' ? null : $shortName,
            $this->accounts,
        );
    }

    /**
     * @param Percentage|numeric-string $percentage The VAT rate, e.g. `'21'`.
     */
    public function percentage(Percentage|string $percentage): self
    {
        return new self($this->from, Percentage::of($percentage), $this->name, $this->shortName, $this->accounts);
    }

    /**
     * The same percentage from another date, e.g. to copy a line with its accounts.
     */
    public function startingOn(DateTimeInterface $from): self
    {
        return new self(
            XmlValue::toDate(XmlValue::date($from)),
            $this->percentage,
            $this->name,
            $this->shortName,
            $this->accounts,
        );
    }

    /**
     * Adds an account line.
     */
    public function account(VatAccountInput $account): self
    {
        return $this->withAccounts(...[...$this->accounts, $account]);
    }

    /**
     * Replaces all account lines.
     */
    public function withAccounts(VatAccountInput ...$accounts): self
    {
        return new self($this->from, $this->percentage, $this->name, $this->shortName, array_values($accounts));
    }

    /**
     * Adds the `<percentage>` element.
     *
     * @internal
     *
     * @throws LogicException When the percentage has no account: Twinfield needs to know where the VAT goes.
     */
    public function appendTo(XmlBuilder $xml, DOMElement $percentages, VatCodeInput $vatCode): void
    {
        if ($this->accounts === []) {
            throw new LogicException(sprintf(
                'VAT code %s: the percentage from %s needs at least one account: add one with account().',
                $vatCode->code,
                $this->from->format('Y-m-d'),
            ));
        }

        $line = $xml->add($percentages, 'percentage');
        $xml->add($line, 'date', XmlValue::date($this->from));
        $xml->add($line, 'percentage', $this->percentage->value);
        $xml->add($line, 'name', $this->name ?? $vatCode->name);
        $xml->add($line, 'shortname', $this->shortName);

        $accounts = $xml->add($line, 'accounts');

        foreach ($this->accounts as $account) {
            $account->appendTo($xml, $accounts);
        }
    }

    /**
     * A VAT rate is 0 to 100 percent.
     *
     * @internal
     */
    public static function validateRate(Percentage $percentage): void
    {
        if ($percentage->isNegative() || $percentage->compare('100') > 0) {
            throw new InvalidArgumentException(sprintf('A VAT percentage must be 0 to 100, %s given.', $percentage));
        }
    }
}
