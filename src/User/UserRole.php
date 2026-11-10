<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The role of a user: what the user may do in Twinfield, e.g. `LVL1` (Level 1).
 *
 *   <role locked="false" name="Level 1" shortname="Level 1" level="2">LVL1</role>
 */
final readonly class UserRole
{
    public function __construct(
        /** Role code, e.g. `LVL1`; pass it to `UserInput::role()`. */
        public string $code,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
        /** Level of the role, e.g. `2`. */
        public int $level,
    ) {
        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('User role code and name must not be empty.');
        }
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $role): self
    {
        if ($role->text() === '') {
            throw $role->invalid('a role code');
        }

        if ($role->attribute('name') === '') {
            throw $role->invalid('a role with a name');
        }

        $shortName = $role->attribute('shortname');

        return new self(
            $role->text(),
            $role->attribute('name'),
            $shortName === '' ? null : $shortName,
            $role->intAttribute('level'),
        );
    }
}
