<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

/**
 * Message codes of Load, Hide and Delete (`HierarchyLoadMessage` in the WSDL).
 */
enum HierarchyLoadMessage: string implements HierarchyMessageCode
{
    case InvalidCode = 'InvalidCode';
    case UserRoleNotAllowedToPerformThisAction = 'UserRoleNotAllowedToPerformThisAction';
}
