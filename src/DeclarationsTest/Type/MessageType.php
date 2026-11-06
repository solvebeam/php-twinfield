<?php

namespace SolveBeam\Twinfield\Declarations\Type;

enum MessageType: string {
    case Error = 'Error';
    case Warning = 'Warning';
    case Informational = 'Informational';
}
