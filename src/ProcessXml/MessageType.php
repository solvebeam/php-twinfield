<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ProcessXml;

/**
 * The `msgtype` attribute of a ProcessXml response element.
 */
enum MessageType: string
{
    case Error = 'error';
    case Warning = 'warning';
}
