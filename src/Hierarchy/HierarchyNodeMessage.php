<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

/**
 * Message codes of a node in a saved reporting structure (`HierarchyNodeMessage` in the WSDL).
 */
enum HierarchyNodeMessage: string implements HierarchyMessageCode
{
    case Touched = 'Touched';
    case InvalidCode = 'InvalidCode';
    case InvalidName = 'InvalidName';
    case InvalidDescription = 'InvalidDescription';
    case NodeConflict = 'NodeConflict';
    case NodeNotUnique = 'NodeNotUnique';
    case BindingConflict = 'BindingConflict';
    case BindingNotUnique = 'BindingNotUnique';
    case ReadOnly = 'ReadOnly';
    case MaximumDepthExceeded = 'MaximumDepthExceeded';
    case BindingInvalid = 'BindingInvalid';
}
