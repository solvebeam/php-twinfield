<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

/**
 * Message codes of Save for the reporting structure as a whole (`HierarchySaveMessage` in the WSDL).
 * Problems with a node or an access right are in HierarchyNodeMessage and HierarchyAccessMessage.
 */
enum HierarchySaveMessage: string implements HierarchyMessageCode
{
    case Touched = 'Touched';
    case InvalidCode = 'InvalidCode';
    case InvalidName = 'InvalidName';
    case InvalidDescription = 'InvalidDescription';
    case InvalidNodes = 'InvalidNodes';
    case InvalidAccessRights = 'InvalidAccessRights';
    case AccessDenied = 'AccessDenied';
    case DefaultInOffice = 'DefaultInOffice';
}
