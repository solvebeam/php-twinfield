<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

/**
 * Access to a reporting structure: none, use it in reports, bind accounts to it, or edit it.
 */
enum HierarchyAccessType: string
{
    case None = 'None';
    case Use = 'Use';
    case Bind = 'Bind';
    case Edit = 'Edit';
}
