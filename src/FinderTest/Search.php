<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Finder;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

#[SoapName('Search')]
#[SoapAction(action: 'http://www.twinfield.com/Search')]
final readonly class Search
{
    public function __construct(
        #[SoapName('type')]
        public string $type,
        #[SoapName('pattern')]
        public string $pattern,
        #[SoapName('field')]
        public int $field,
        #[SoapName('firstRow')]
        public int $firstRow,
        #[SoapName('maxRows')]
        public int $maxRows,
    ) {
    }
}
