<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A VAT code to create or update. Immutable: every method returns a new input.
 *
 *   $input = VatCodeInput::sales('VH', 'BTW 21%')
 *       ->shortName('VH 21%')
 *       ->percentage(
 *           VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21')
 *               ->account(VatAccountInput::vat('1530')->group('NL', 'NL1A')),
 *       );
 *
 * For an existing VAT code, start from `$vatCode->edit()`: it has all current percentages and
 * accounts. A new rate from a date on, with the same accounts:
 *
 *   $vatCodes->update($vatCode->edit()->newPercentageFrom(new DateTimeImmutable('2027-01-01'), '9', 'BTW 9%'));
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/vat/
 */
final readonly class VatCodeInput
{
    /**
     * @param array<int, VatPercentageInput> $percentages By start date as int `yyyymmdd`.
     */
    private function __construct(
        public string $code,
        public string $name,
        public VatCodeType $type,
        public ?string $shortName,
        private array $percentages,
    ) {
        XmlValue::code($code);

        if ($name === '') {
            throw new InvalidArgumentException('VAT code name must not be empty.');
        }

        XmlValue::maxLength('VAT code name', $name, 40);

        if ($shortName !== null) {
            XmlValue::maxLength('VAT code short name', $shortName, 20);
        }
    }

    /**
     * @param string $code VAT code: A-Z, 0-9, `_`, `-` or `+`, e.g. `VH`.
     * @param string $name At most 40 characters.
     */
    public static function create(string $code, string $name, VatCodeType $type): self
    {
        return new self($code, $name, $type, null, []);
    }

    /**
     * A VAT code for sales, e.g. `VatCodeInput::sales('VH', 'BTW 21%')`.
     */
    public static function sales(string $code, string $name): self
    {
        return self::create($code, $name, VatCodeType::Sales);
    }

    /**
     * A VAT code for purchases, e.g. `VatCodeInput::purchase('IH', 'BTW 21%')`.
     */
    public static function purchase(string $code, string $name): self
    {
        return self::create($code, $name, VatCodeType::Purchase);
    }

    /**
     * At most 40 characters.
     */
    public function name(string $name): self
    {
        return new self($this->code, $name, $this->type, $this->shortName, $this->percentages);
    }

    /**
     * At most 20 characters. Null or an empty string clears it.
     */
    public function shortName(?string $shortName): self
    {
        $shortName = $shortName === '' ? null : $shortName;

        return new self($this->code, $this->name, $this->type, $shortName, $this->percentages);
    }

    public function type(VatCodeType $type): self
    {
        return new self($this->code, $this->name, $type, $this->shortName, $this->percentages);
    }

    /**
     * Adds a percentage, or replaces the percentage with the same start date.
     */
    public function percentage(VatPercentageInput $percentage): self
    {
        $percentages = $this->percentages;
        $percentages[self::key($percentage->from)] = $percentage;
        ksort($percentages);

        return new self($this->code, $this->name, $this->type, $this->shortName, $percentages);
    }

    /**
     * A new rate from a date on, with the accounts (and names, unless given) of the percentage
     * that applies the day before. The usual way to change a rate, e.g. from 6% to 9%:
     *
     *   $vatCode->edit()->newPercentageFrom(new DateTimeImmutable('2019-01-01'), '9', 'BTW 9%', 'V 9%');
     *
     * @param Percentage|numeric-string $percentage The new VAT rate.
     *
     * @throws InvalidArgumentException When no percentage starts before the date: use percentage().
     */
    public function newPercentageFrom(
        DateTimeInterface $from,
        Percentage|string $percentage,
        ?string $name = null,
        ?string $shortName = null,
    ): self {
        $key = self::key($from);
        $previous = null;

        foreach ($this->percentages as $start => $line) {
            if ($start < $key) {
                $previous = $line;
            }
        }

        if ($previous === null) {
            throw new InvalidArgumentException(sprintf(
                'VAT code %s has no percentage before %s to copy the accounts from: use percentage().',
                $this->code,
                $from->format('Y-m-d'),
            ));
        }

        $line = $previous->startingOn($from)->percentage($percentage);
        $line = $name === null ? $line : $line->name($name);
        $line = $shortName === null ? $line : $line->shortName($shortName);

        return $this->percentage($line);
    }

    /**
     * The percentage with this start date, or null.
     */
    public function percentageFrom(DateTimeInterface $from): ?VatPercentageInput
    {
        return $this->percentages[self::key($from)] ?? null;
    }

    /**
     * @return list<VatPercentageInput> Sorted by start date, oldest first.
     */
    public function percentages(): array
    {
        return array_values($this->percentages);
    }

    /**
     * The `<vat>` request to save this VAT code in a company.
     *
     * @throws LogicException When the input has no percentages, or a percentage has no account.
     *
     * @internal
     */
    public function toXml(string $company): XmlBuilder
    {
        if ($this->percentages === []) {
            throw new LogicException(sprintf(
                'VAT code %s needs at least one percentage: add one with percentage().',
                $this->code,
            ));
        }

        $xml = XmlBuilder::create('vat');
        $vat = $xml->root();

        $xml->add($vat, 'office', $company);
        $xml->add($vat, 'code', $this->code);
        $xml->add($vat, 'name', $this->name);
        $xml->add($vat, 'shortname', $this->shortName);
        $xml->add($vat, 'type', $this->type->value);

        $percentages = $xml->add($vat, 'percentages');

        foreach ($this->percentages as $percentage) {
            $percentage->appendTo($xml, $percentages, $this);
        }

        return $xml;
    }

    private static function key(DateTimeInterface $date): int
    {
        return (int) XmlValue::date($date);
    }
}
