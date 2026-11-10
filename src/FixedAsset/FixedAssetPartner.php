<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use DateTimeImmutable;

/**
 * A partner (e.g. the supplier or lessor) of a fixed asset from a date on.
 */
final readonly class FixedAssetPartner
{
    public function __construct(
        /** GUID of the partner. */
        public string $id,
        public DateTimeImmutable $from,
    ) {
    }
}
