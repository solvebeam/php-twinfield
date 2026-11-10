<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankFeed;

use DateTimeImmutable;
use SolveBeam\Twinfield\Guid;

/**
 * A PSD2 bank feed (REST API): the consent of the bank to send the transactions of a bank account to
 * Twinfield, and until when it is valid.
 */
final readonly class Psd2Feed
{
    public function __construct(
        /** GUID of the feed. */
        public string $id,
        /** When the account holder gave consent. */
        public DateTimeImmutable $consentOn,
        /** When the consent expires; the holder must renew it before. */
        public DateTimeImmutable $expiresOn,
        public Psd2FeedAccount $account,
    ) {
        Guid::of($id);
    }
}
