<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

use SolveBeam\Twinfield\Soap\SoapName;

#[SoapName('SearchResponse')]
final readonly class SearchResponse
{
    public function __construct(
        #[SoapName('data')]
        public FinderData|null $data = null,
    ) {}
}

