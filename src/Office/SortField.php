<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A sort field of the credit management lists, e.g. `fin.trs.line.datedue` ascending.
 */
final readonly class SortField
{
    public function __construct(
        /** Browse field, e.g. `fin.trs.line.datedue`. */
        public string $field,
        public SortOrder $order,
    ) {
        if ($field === '') {
            throw new InvalidArgumentException('Sort field must not be empty.');
        }
    }

    /**
     * Null for an empty `<field>`: that sort position is not used, which is valid in Twinfield.
     */
    public static function nullable(XmlElement $field): ?self
    {
        if ($field->text() === '') {
            return null;
        }

        return new self($field->text(), $field->enumAttribute('order', SortOrder::class));
    }
}
