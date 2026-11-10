<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

enum UndoMatchMessageType: string
{
    case Error = 'Error';
    case Warning = 'Warning';
    case Informational = 'Informational';
}
