<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * Credit management of a customer (`creditmanagement`): credit limit and payment reminders.
 */
final readonly class CreditManagement
{
    public function __construct(
        /** Credit manager (user code); null when not set. */
        public ?string $responsibleUser,
        /** Credit limit, `0.00` when there is none. */
        public Amount $creditLimit,
        public SendReminder $sendReminder,
        /** Email address for reminders; null when not set. */
        public ?string $reminderEmail,
        /** True when the projects of the customer are blocked in time & expenses. */
        public bool $blocked,
        /** Right of use (free text 1); null when not set. */
        public ?bool $rightOfUse,
        /** Segment code (free text 2), at most 40 characters; null when not set. */
        public ?string $segmentCode,
        public ?string $comment,
    ) {
    }

    /**
     * From the `<creditmanagement>` element of a customer.
     */
    public static function fromXml(XmlElement $credit): self
    {
        $limit = $credit->child('basecreditlimit');

        try {
            $creditLimit = Amount::fromString($limit->text());
        } catch (InvalidArgumentException) {
            throw $limit->invalid('an amount');
        }

        // Optional in Twinfield: empty elements, e.g. <freetext1/>, when not set.
        return new self(
            responsibleUser: $credit->nullableString('responsibleuser'),
            creditLimit: $creditLimit,
            sendReminder: $credit->enum('sendreminder', SendReminder::class),
            reminderEmail: $credit->nullableString('reminderemail'),
            blocked: $credit->bool('blocked'),
            rightOfUse: $credit->nullableBool('freetext1'),
            segmentCode: $credit->nullableString('freetext2'),
            comment: $credit->nullableString('comment'),
        );
    }
}
