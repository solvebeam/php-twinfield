<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use DateTimeImmutable;

/**
 * An action in the history of a fixed asset, e.g. that it was depreciated, with the transactions it
 * posted.
 */
final readonly class FixedAssetAction
{
    /**
     * @param list<FixedAssetTransaction> $transactions
     */
    public function __construct(
        public FixedAssetActionType $type,
        public DateTimeImmutable $timestamp,
        /** Code of the user, e.g. `JDOE`. */
        public string $user,
        public array $transactions,
    ) {
    }
}
