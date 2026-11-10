<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Login status of a user (`user/account`): locked, last logon, single sign-on, password expiry
 * and the period in which the account is disabled.
 *
 *   if ($user->account->isDisabledAt(new DateTimeImmutable())) { … }
 */
final readonly class UserAccount
{
    public function __construct(
        /** True when the account is locked, e.g. after too many failed logons. */
        public bool $locked,
        /** Last logon; null for a user who never logged on. */
        public ?DateTimeImmutable $lastLogon,
        /** Number of failed logons in a row. */
        public int $failedLogons,
        public SingleSignOn $singleSignOn,
        /** Date the password expires; null when it does not expire. Not used with single sign-on. */
        public ?DateTimeImmutable $passwordExpires,
        /** Start of the period the account is disabled; null when not disabled. */
        public ?DateTimeImmutable $disabledFrom,
        /** End of the period the account is disabled; null when not disabled. */
        public ?DateTimeImmutable $disabledUntil,
    ) {
        if ($failedLogons < 0) {
            throw new InvalidArgumentException(sprintf('Failed logons must be 0 or more, %d given.', $failedLogons));
        }

        if ($disabledFrom !== null && $disabledUntil !== null && $disabledUntil < $disabledFrom) {
            throw new InvalidArgumentException(sprintf(
                'The account is disabled until %s, before it is disabled from %s.',
                $disabledUntil->format('Y-m-d H:i:s'),
                $disabledFrom->format('Y-m-d H:i:s'),
            ));
        }
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $account): self
    {
        $disable = $account->child('disable');

        return new self(
            locked: $account->boolAttribute('locked'),
            lastLogon: $account->nullableDateTimeAttribute('lastlogon'),
            failedLogons: $account->intAttribute('failedlogons'),
            singleSignOn: $account->enum('sso', SingleSignOn::class),
            passwordExpires: $account->nullableDate('expire'),
            disabledFrom: $disable->nullableDateTime('from'),
            disabledUntil: $disable->nullableDateTime('to'),
        );
    }

    /**
     * True when the moment falls in the period the account is disabled. Without an end, the
     * account stays disabled.
     */
    public function isDisabledAt(DateTimeInterface $moment): bool
    {
        if ($this->disabledFrom === null || $moment < $this->disabledFrom) {
            return false;
        }

        return $this->disabledUntil === null || $moment < $this->disabledUntil;
    }
}
