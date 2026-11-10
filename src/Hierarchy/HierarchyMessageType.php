<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

enum HierarchyMessageType: string
{
    case Error = 'Error';
    case Warning = 'Warning';
    case Informational = 'Informational';
}
