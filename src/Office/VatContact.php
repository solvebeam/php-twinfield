<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A contact for the VAT declarations (`vatmanagement/txp`, `int` or `seb`). Every field is
 * optional in Twinfield, so each one is null when not set.
 */
final readonly class VatContact
{
    public function __construct(
        /** Identification (`cpid`), e.g. the VAT number without country code for the taxpayer. */
        public ?string $id,
        /** Identification as sole trader (`cpid_st`); only the taxpayer has it. */
        public ?string $soleTraderId,
        /** Contact name (`cpname`). */
        public ?string $name,
        /** Contact telephone (`cptel`). */
        public ?string $telephone,
    ) {
    }

    /**
     * @param bool $hasSoleTraderId True for `txp`, the only contact with a `cpid_st` element.
     *
     * @internal
     */
    public static function fromXml(XmlElement $contact, bool $hasSoleTraderId = false): self
    {
        return new self(
            id: $contact->nullableString('cpid'),
            soleTraderId: $hasSoleTraderId ? $contact->nullableString('cpid_st') : null,
            name: $contact->nullableString('cpname'),
            telephone: $contact->nullableString('cptel'),
        );
    }
}
