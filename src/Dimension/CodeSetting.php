<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A project setting with a code, such as the customer, rate or authoriser of an activity.
 *
 *   CodeSetting::inherit();                         // from project or user level
 *   new CodeSetting('1010');                        // fixed, may be changed in a timesheet
 *   new CodeSetting('T001', ChangeMode::Disallow);  // fixed, may not be changed
 */
final readonly class CodeSetting
{
    public function __construct(
        /** Null when no code is set, e.g. when the setting is inherited. */
        public ?string $code,
        public ChangeMode $change = ChangeMode::Allow,
    ) {
        if ($code !== null) {
            XmlValue::code($code);
        }
    }

    public static function inherit(): self
    {
        return new self(null, ChangeMode::Inherit);
    }
}
