<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

/**
 * Whether hours on an activity or project are billable, and whether they count
 * for the productivity ratio (`forratio`).
 */
final readonly class BillableSetting
{
    public function __construct(
        /** Null when not set: Twinfield leaves the value empty when the setting is inherited. */
        public ?bool $billable,
        public ChangeMode $change = ChangeMode::Allow,
        public bool $forRatio = false,
    ) {
    }

    public static function inherit(): self
    {
        return new self(null, ChangeMode::Inherit);
    }
}
