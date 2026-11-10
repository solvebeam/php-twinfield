<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

/**
 * Message codes of Bind (`HierarchyBindMessage` in the WSDL).
 */
enum HierarchyBindMessage: string implements HierarchyMessageCode
{
    case BindingConflict = 'BindingConflict';
    case BindingNotUnique = 'BindingNotUnique';
    case InvalidNode = 'InvalidNode';
    case ReadOnly = 'ReadOnly';
    case BindingInvalid = 'BindingInvalid';
}
