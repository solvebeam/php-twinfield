<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

enum PayAndCollectMessageType: string
{
    case Error = 'Error';
    case Warning = 'Warning';
    case Informational = 'Informational';
}
