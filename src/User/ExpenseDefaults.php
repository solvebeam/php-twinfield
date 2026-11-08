<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Default dimensions for the hour journals of a user (`user/expenses`, dimension1–4). Each is
 * null when not set.
 */
final readonly class ExpenseDefaults
{
    public function __construct(
        /** Dimension 1: general ledger account. */
        public ?NamedReference $generalLedgerAccount = null,
        /** Dimension 2: cost center. */
        public ?NamedReference $costCenter = null,
        /** Dimension 3: project. */
        public ?NamedReference $project = null,
        /** Dimension 4: activity. */
        public ?NamedReference $activity = null,
    ) {
    }

    public static function fromXml(XmlElement $expenses): self
    {
        return new self(
            NamedReference::nullable($expenses->child('dimension1')),
            NamedReference::nullable($expenses->child('dimension2')),
            NamedReference::nullable($expenses->child('dimension3')),
            NamedReference::nullable($expenses->child('dimension4')),
        );
    }
}
