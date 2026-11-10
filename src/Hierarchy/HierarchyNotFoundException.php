<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use RuntimeException;

/**
 * There is no reporting structure with this code in the organisation (message code InvalidCode).
 */
final class HierarchyNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $hierarchyCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
    ) {
        parent::__construct(sprintf('Reporting structure %s not found: %s', $hierarchyCode, $reason));
    }
}
