<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Http;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

final class WriteBlockedException extends RuntimeException implements ClientExceptionInterface
{
}
