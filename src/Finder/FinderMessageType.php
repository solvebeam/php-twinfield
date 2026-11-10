<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

enum FinderMessageType: string
{
    case Error = 'Error';
    case Warning = 'Warning';
    case Informational = 'Informational';
}
