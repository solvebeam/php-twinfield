<?php

namespace SolveBeam\Twinfield\Declarations\Type;

enum LoadMessage: string {
    case NoAccess = 'NoAccess';
    case InvalidCode = 'InvalidCode';
    case InvalidParameter = 'InvalidParameter';
    case NotSupported = 'NotSupported';
    case InvalidDocumentType = 'InvalidDocumentType';
    case ParameterIsRequired = 'ParameterIsRequired';
    case ParametersAreRequired = 'ParametersAreRequired';
}
