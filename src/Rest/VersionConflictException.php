<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

/**
 * HTTP 412 Precondition Failed: the resource changed since you read it (its `version` is outdated).
 * Read it again and redo the change.
 */
final class VersionConflictException extends RestException
{
}
