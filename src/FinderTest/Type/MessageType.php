<?php

namespace SolveBeam\Twinfield\Finder\Type;

enum MessageType: string {
    case Error = 'Error';
    case Warning = 'Warning';
    case Informational = 'Informational';
}
