<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A quantity line (`<line type="QUANTITY">`) to send, e.g. kilometres; add it with
 * `TimeEntryInput::quantity()`.
 */
final readonly class QuantityLineInput
{
    /**
     * @param numeric-string $quantity
     */
    public function __construct(
        /** The number of units, e.g. `'1.5'`: a numeric string, never a float. */
        public string $quantity,
        /** Rate code, e.g. `KM`; null: Twinfield takes the default rate. */
        public ?string $rate = null,
        /** Null: Twinfield decides, from the project, activity or rate. */
        public ?bool $billable = null,
    ) {
        if (preg_match('~^\d+(?:\.\d+)?$~', $quantity) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a quantity: use a numeric string of 0 or more with a dot, e.g. "1.5".',
                $quantity,
            ));
        }

        if ($rate !== null) {
            XmlValue::code($rate);
        }
    }
}
