<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration;

/**
 * Code of a message from a status command (`SetStatusMessages`).
 */
enum DeclarationStatusMessage: string
{
    case NoAccess = 'NoAccess';
    case DocumentDoesNotExist = 'DocumentDoesNotExist';

    /** The return is not in a status from which this change is possible. Twinfield's spelling. */
    case StartStatusInCorrectForTransition = 'StartStatusInCorrectForTransition';

    case UnableToPerformTransition = 'UnableToPerformTransition';
}
