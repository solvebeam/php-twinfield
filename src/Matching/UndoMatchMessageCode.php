<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

/**
 * Message codes of UndoMatch (`UndoSetByTypeResult` in the WSDL).
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/undo-match/
 */
enum UndoMatchMessageCode: string
{
    /** The dimension can not be found. */
    case DimensionNotFoundByType = 'DimensionNotFoundByType';

    /** The dimension is deleted. */
    case DimensionDeleted = 'DimensionDeleted';

    /** The dimension can not be found. */
    case DimensionNotFoundByLevel = 'DimensionNotFoundByLevel';

    /** There is no match set with this number. */
    case MatchNotFound = 'MatchNotFound';

    /** The matching transactions could not be deleted. */
    case MatchingTransactionsCouldNotBeDeleted = 'MatchingTransactionsCouldNotBeDeleted';
}
