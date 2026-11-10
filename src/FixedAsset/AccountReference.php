<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

/**
 * A general ledger account by GUID, code and name.
 */
final readonly class AccountReference
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
    ) {
    }
}
