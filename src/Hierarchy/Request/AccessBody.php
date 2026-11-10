<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapName;

/**
 * `HierarchyAccess` in a Save request. Fields in WSDL order; `Messages` is left out.
 *
 * @internal
 */
final readonly class AccessBody
{
    public function __construct(
        #[SoapName('Type')]
        public string $type,
        #[SoapName('OfficeCode')]
        public ?string $officeCode,
        #[SoapName('RoleCode')]
        public ?string $roleCode,
        #[SoapName('UserCode')]
        public ?string $userCode,
        /** `true` or `false`. */
        #[SoapName('AccessRightTabAccesible')]
        public string $accessRightTabAccessible,
    ) {
    }
}
