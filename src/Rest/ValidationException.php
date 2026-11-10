<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

/**
 * HTTP 400 Bad Request or 422 Unprocessable Entity: invalid parameters or data, e.g. an article code
 * that is already in use.
 */
final class ValidationException extends RestException
{
}
