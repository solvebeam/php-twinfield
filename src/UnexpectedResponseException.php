<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield;

use RuntimeException;

/**
 * Twinfield answered with something this library does not expect,
 * e.g. a missing element or an empty required value.
 */
final class UnexpectedResponseException extends RuntimeException
{
}
