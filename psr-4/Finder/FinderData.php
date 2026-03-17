<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

use SolveBeam\Twinfield\Soap\SoapName;

final readonly class FinderData
{
    public function __construct(
        #[SoapName('TotalRows')]
        public int $totalRows,
    ) {}
}
