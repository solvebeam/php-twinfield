<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CommandQuery;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * A query for a Command & Query web service, e.g. `GetPeriods` of the PeriodService.
 *
 *   new Query('GetPeriods', ['Year' => 2021]);
 *
 * Give the fields in the order of the service's WSDL (usually alphabetical):
 * the WCF services do not accept fields in another order. `null` fields are left out,
 * `Nil::Value` is sent as `i:nil="true"` and an array is a complex element with its own fields.
 *
 * @see https://developers.twinfield.com/documentation/api/types/command-and-query-webservices
 */
final readonly class Query
{
    /**
     * @param array<string, int|string|bool|DateTimeInterface|Nil|array<string, mixed>|null> $fields
     */
    public function __construct(
        public string $type,
        public array $fields = [],
    ) {
        if ($type === '') {
            throw new InvalidArgumentException('Query type must not be empty.');
        }
    }
}
