<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use InvalidArgumentException;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Time & Expenses settings of a user (`user/time`).
 */
final readonly class TimeSettings
{
    public function __construct(
        /** User who authorises the hours of this user; null when not set. */
        public ?NamedReference $authoriser,
        /** Work hours per week; null when not set. */
        public ?int $weekHours,
    ) {
        if ($weekHours !== null && ($weekHours < 0 || $weekHours > 168)) {
            throw new InvalidArgumentException(sprintf('Week hours must be 0 to 168, %d given.', $weekHours));
        }
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $time): self
    {
        // Empty for users that never had week hours set, 0 for others.
        $weekHours = $time->child('weekhours')->text() === '' ? null : $time->int('weekhours');

        return new self(NamedReference::nullable($time->child('authoriser')), $weekHours);
    }
}
