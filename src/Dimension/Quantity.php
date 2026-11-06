<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * An extra quantity a user can enter on a timesheet line, such as kilometres.
 *
 *   Quantity::create('KM')->rate('KM')->billable(true, locked: true)->mandatory();
 */
final readonly class Quantity
{
    public function __construct(
        /** Label, at most 36 characters. */
        public string $label,
        /** Rate code; null when the quantity has no rate. */
        public ?string $rate = null,
        public bool $billable = false,
        /** True when users may not change `billable` in a timesheet. */
        public bool $billableLocked = false,
        public bool $mandatory = false,
    ) {
        if ($label === '') {
            throw new InvalidArgumentException('Quantity label must not be empty.');
        }

        XmlValue::maxLength('quantity label', $label, 36);

        if ($rate !== null) {
            XmlValue::code($rate);
        }
    }

    public static function create(string $label): self
    {
        return new self($label);
    }

    public function rate(?string $rate): self
    {
        return new self($this->label, $rate, $this->billable, $this->billableLocked, $this->mandatory);
    }

    public function billable(bool $billable = true, bool $locked = false): self
    {
        return new self($this->label, $this->rate, $billable, $locked, $this->mandatory);
    }

    public function mandatory(bool $mandatory = true): self
    {
        return new self($this->label, $this->rate, $this->billable, $this->billableLocked, $mandatory);
    }
}
