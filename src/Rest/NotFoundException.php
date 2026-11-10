<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

/**
 * HTTP 404 Not Found: the resource (article, budget, VAT code, …) does not exist in the company.
 */
final class NotFoundException extends RestException
{
}
