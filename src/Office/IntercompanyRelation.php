<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\DimensionReference;

/**
 * An intercompany relation with another office (`intercompany/outs/out` or `ins/in`): the
 * dimensions on which intercompany amounts are booked.
 */
final readonly class IntercompanyRelation
{
    public function __construct(
        /** The other office. Its name is empty when the user has no access to it. */
        public NamedReference $office,
        /** Transaction type of incoming relations, e.g. `RC` (current account); null for outgoing ones. */
        public ?NamedReference $transaction,
        /** General ledger account, e.g. `16401`. */
        public ?DimensionReference $dimension1,
        public ?DimensionReference $dimension2,
        public ?DimensionReference $dimension3,
        public ?string $description,
        /** True when the relation runs through another office, see $indirectOffice. */
        public bool $indirect,
        /** Office in between for an indirect outgoing relation; null when not set, and for incoming ones. */
        public ?NamedReference $indirectOffice,
    ) {
    }

    /**
     * Outgoing relations (`<out>`) have an `indirectoffice` element, incoming ones (`<in>`) a
     * `transaction` element instead.
     *
     * @param bool $outgoing True for `<out>`.
     */
    public static function fromXml(XmlElement $relation, bool $outgoing): self
    {
        return new self(
            office: NamedReference::fromXml($relation->child('office')),
            transaction: $outgoing ? null : NamedReference::fromXml($relation->child('transaction')),
            dimension1: self::dimension($relation->child('dim1')),
            dimension2: self::dimension($relation->child('dim2')),
            dimension3: self::dimension($relation->child('dim3')),
            description: $relation->nullableString('description'),
            indirect: $relation->bool('indirect'),
            indirectOffice: $outgoing ? NamedReference::nullable($relation->child('indirectoffice')) : null,
        );
    }

    /**
     * `<dim1 name="…" shortname="" dimensiontype="BAS">16401</dim1>`; null when empty.
     */
    private static function dimension(XmlElement $dimension): ?DimensionReference
    {
        if ($dimension->text() === '') {
            return null;
        }

        $type = $dimension->attribute('dimensiontype');
        $name = $dimension->attribute('name');

        return new DimensionReference($dimension->text(), $type === '' ? null : $type, $name === '' ? null : $name);
    }
}
