<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A dimension the office uses for a system purpose, e.g. the accounts receivable control
 * account: `<accountsreceivable readonly="true" name="Trade debtors" shortname="">13000</accountsreceivable>`.
 */
final readonly class SystemDimension
{
    public function __construct(
        /** Dimension code, e.g. a general ledger account `13000` or a cost center `00000`. */
        public string $code,
        /** Null when Twinfield gives an empty name. */
        public ?string $name,
        /** True when the setting can no longer be changed, e.g. because it is in use. */
        public bool $readOnly,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('System dimension code must not be empty.');
        }
    }

    /**
     * Null for an empty element: the system dimension is not set, which is valid in Twinfield.
     *
     * @internal
     */
    public static function nullable(XmlElement $element): ?self
    {
        if ($element->text() === '') {
            return null;
        }

        $name = $element->attribute('name');

        return new self($element->text(), $name === '' ? null : $name, $element->boolAttribute('readonly'));
    }
}
