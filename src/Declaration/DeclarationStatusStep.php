<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration;

/**
 * Status of a return (`Status/StepIndex`). Live, only `Created` and `Approved` were seen.
 */
enum DeclarationStatusStep: int
{
    case Created = 1;
    case Modified = 2;
    case Sending = 3;
    case ErrorWhileSending = 4;
    case Sent = 5;
    case Rejected = 6;
    case Approved = 7;
    case Authorized = 8;
}
