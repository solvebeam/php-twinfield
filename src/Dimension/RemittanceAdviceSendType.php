<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

/**
 * Where Twinfield puts the remittance advice (`remittanceadvice/sendtype`).
 */
enum RemittanceAdviceSendType: string
{
    case ToFileManager = 'ToFileManager';
    case ByEmail = 'ByEmail';
}
