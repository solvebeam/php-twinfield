<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration;

enum DeclarationMessageType: string
{
    case Error = 'Error';
    case Warning = 'Warning';
    case Informational = 'Informational';
}
