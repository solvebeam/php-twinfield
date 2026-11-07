<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionGroup;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A dimension in a dimension group, as Twinfield returns it, e.g. customer `DEB 1000`.
 */
final readonly class DimensionGroupDimension
{
    public function __construct(
        /** Dimension type, e.g. `DEB`, `CRD`, `KPL` or `PNL`. */
        public string $type,
        /** Code of the dimension, e.g. `1000`. */
        public string $code,
        /** Name of the dimension, e.g. the customer name; null when Twinfield gives none. */
        public ?string $name,
    ) {
        if ($type === '' || $code === '') {
            throw new InvalidArgumentException('Dimension group dimension type and code must not be empty.');
        }
    }

    /**
     * From a `<dimension>` element in `<dimensions>`, e.g.
     * `<dimension><type name="Debiteuren">DEB</type><code name="Example B.V.">1000</code></dimension>`.
     */
    public static function fromXml(XmlElement $dimension): self
    {
        $code = $dimension->child('code');

        // Twinfield adds the name as attribute; it may be empty or left out.
        $name = $code->element->getAttribute('name');

        return new self(
            type: $dimension->string('type'),
            code: $dimension->string('code'),
            name: $name === '' ? null : $name,
        );
    }

    /**
     * Whether this is the dimension with this type and code.
     */
    public function is(string $type, string $code): bool
    {
        return $this->type === $type && $this->code === $code;
    }
}
