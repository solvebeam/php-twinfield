<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

/**
 * Message codes of Unbind (`HierarchyUnbindMessage` in the WSDL).
 */
enum HierarchyUnbindMessage: string implements HierarchyMessageCode
{
    case InvalidNode = 'InvalidNode';
}
