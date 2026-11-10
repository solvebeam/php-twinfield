<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

/**
 * Message codes of an access right in a saved reporting structure (`HierarchyAccessMessage` in the WSDL).
 */
enum HierarchyAccessMessage: string implements HierarchyMessageCode
{
    case InvalidOffice = 'InvalidOffice';
    case InvalidRole = 'InvalidRole';
    case InvalidUser = 'InvalidUser';
    case Redundant = 'Redundant';
    case InvalidRoleUser = 'InvalidRoleUser';
    case InvalidRoleAccess = 'InvalidRoleAccess';
    case InvalidUserAccess = 'InvalidUserAccess';
    case DefaultInOffice = 'DefaultInOffice';
    case InvalidRoleEditAccess = 'InvalidRoleEditAccess';
    case InvalidUserEditAccess = 'InvalidUserEditAccess';
}
