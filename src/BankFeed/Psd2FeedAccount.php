<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankFeed;

/**
 * The bank account of a PSD2 feed, and the company it belongs to.
 */
final readonly class Psd2FeedAccount
{
    public function __construct(
        /** GUID of the feed account. */
        public string $id,
        /** GUID of the bank account in Twinfield. */
        public string $bankAccountId,
        /** GUID of the company. */
        public string $companyId,
        /** Code of the company, e.g. `1000`. */
        public string $companyCode,
        public string $companyName,
        public string $iban,
    ) {
    }
}
