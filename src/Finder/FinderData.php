<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

use SolveBeam\Twinfield\Data\ArrayOfString;
use SolveBeam\Twinfield\Data\ArrayOfArrayOfString;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * `FinderData` in the response of the Finder.
 *
 * @internal Use `FinderResult`.
 */
final readonly class FinderData
{
    public function __construct(
        #[SoapName('TotalRows')]
        public int $totalRows,
        #[SoapName('Columns')]
        public ?ArrayOfString $columns = null,
        #[SoapName('Items')]
        public ?ArrayOfArrayOfString $items = null,
    ) {
    }
}
