<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration;

/**
 * Code of a message from a query (`LoadMessage`). `ParameterIsRequired` and
 * `ParametersAreRequired` are in the WSDL, not in the documentation.
 */
enum DeclarationLoadMessage: string
{
    case NoAccess = 'NoAccess';

    /** E.g. `*` as the company code of GetNumberOfDeclarations. */
    case InvalidCode = 'InvalidCode';

    /** E.g. a negative start index. */
    case InvalidParameter = 'InvalidParameter';

    case NotSupported = 'NotSupported';

    /** E.g. the payment reference of an ICT return. */
    case InvalidDocumentType = 'InvalidDocumentType';

    case ParameterIsRequired = 'ParameterIsRequired';
    case ParametersAreRequired = 'ParametersAreRequired';
}
