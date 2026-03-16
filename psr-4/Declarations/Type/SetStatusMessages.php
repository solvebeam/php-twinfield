<?php

namespace SolveBeam\Twinfield\Declarations\Type;

enum SetStatusMessages: string {
    case NoAccess = 'NoAccess';
    case DocumentDoesNotExist = 'DocumentDoesNotExist';
    case StartStatusInCorrectForTransition = 'StartStatusInCorrectForTransition';
    case UnableToPerformTransition = 'UnableToPerformTransition';
}
