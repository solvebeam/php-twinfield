<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Access right to a reporting structure for a user, a role or a company, or (all empty) for
 * everyone else. Live, a reporting structure has one access right per user and a last one for
 * everyone else, e.g. `Edit` for SUPER and `None` for everyone else.
 */
final readonly class HierarchyAccess
{
    public function __construct(
        public HierarchyAccessType $type,
        /** Null when the access right is not for one company. */
        public ?string $officeCode,
        /** Null when the access right is not for a role. */
        public ?string $roleCode,
        /** Null when the access right is not for a user. */
        public ?string $userCode,
        /**
         * Twinfield's `AccessRightTabAccesible`, undocumented. Live, true for the access rights of
         * a user and false for the one of everyone else.
         */
        public bool $accessRightTabAccessible,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResponse(XmlElement $access): self
    {
        return new self(
            type: $access->enum('Type', HierarchyAccessType::class),
            // Empty elements: the access right is not for a company, role or user.
            officeCode: $access->nullableString('OfficeCode'),
            roleCode: $access->nullableString('RoleCode'),
            userCode: $access->nullableString('UserCode'),
            accessRightTabAccessible: $access->bool('AccessRightTabAccesible'),
        );
    }

    /**
     * Input with the values of this access right, for HierarchyInput::accessRights().
     */
    public function edit(): HierarchyAccessInput
    {
        return new HierarchyAccessInput(
            type: $this->type,
            officeCode: $this->officeCode,
            roleCode: $this->roleCode,
            userCode: $this->userCode,
        );
    }
}
