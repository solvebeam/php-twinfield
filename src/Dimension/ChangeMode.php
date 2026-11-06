<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Whether a user may change a project setting (authoriser, customer, billable, rate)
 * when entering a timesheet, or whether it is inherited from the project or user level.
 *
 * In the XML this is the pair of `locked` and `inherit` attributes:
 *
 * | ChangeMode | locked | inherit |
 * |------------|--------|---------|
 * | Allow      | false  | false   |
 * | Disallow   | true   | false   |
 * | Inherit    | true   | true    |
 */
enum ChangeMode
{
    case Allow;
    case Disallow;
    case Inherit;

    /**
     * @param string $path Element, for the error message.
     */
    public static function fromXml(bool $locked, bool $inherit, string $path): self
    {
        return match (true) {
            !$locked && !$inherit => self::Allow,
            $locked && !$inherit => self::Disallow,
            $locked && $inherit => self::Inherit,
            default => throw new UnexpectedResponseException(sprintf(
                '%s has inherit="true" with locked="false", which is not a valid change mode.',
                $path,
            )),
        };
    }

    public function locked(): bool
    {
        return $this !== self::Allow;
    }

    public function inherit(): bool
    {
        return $this === self::Inherit;
    }
}
