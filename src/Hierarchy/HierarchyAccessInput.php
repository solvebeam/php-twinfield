<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use InvalidArgumentException;

/**
 * An access right to save with a reporting structure, see HierarchyInput::accessRights().
 *
 *   HierarchyAccessInput::user('SUPER', HierarchyAccessType::Edit);
 *   HierarchyAccessInput::role('LVL1', HierarchyAccessType::Use);
 *   HierarchyAccessInput::everyone(HierarchyAccessType::None);
 */
final readonly class HierarchyAccessInput
{
    public function __construct(
        public HierarchyAccessType $type,
        public ?string $officeCode = null,
        public ?string $roleCode = null,
        public ?string $userCode = null,
    ) {
        foreach (['office' => $officeCode, 'role' => $roleCode, 'user' => $userCode] as $field => $code) {
            if ($code === '') {
                throw new InvalidArgumentException(sprintf(
                    'Leave out the %s code instead of passing an empty one.',
                    $field,
                ));
            }
        }
    }

    public static function user(string $userCode, HierarchyAccessType $type): self
    {
        return new self($type, userCode: $userCode);
    }

    public static function role(string $roleCode, HierarchyAccessType $type): self
    {
        return new self($type, roleCode: $roleCode);
    }

    /**
     * The access right of everyone without an access right of their own.
     */
    public static function everyone(HierarchyAccessType $type): self
    {
        return new self($type);
    }

    /**
     * @internal
     */
    public function toBody(): Request\AccessBody
    {
        return new Request\AccessBody(
            type: $this->type->value,
            officeCode: $this->officeCode,
            roleCode: $this->roleCode,
            userCode: $this->userCode,
            // Undocumented and required by the WSDL. Live, true for the access rights of a user
            // and false for the one of everyone else; the SDK sends the same.
            accessRightTabAccessible: $this->officeCode !== null || $this->roleCode !== null || $this->userCode !== null
                ? 'true'
                : 'false',
        );
    }
}
