<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapArray;

/**
 * `ArrayOfDetailSettings`.
 *
 * @internal
 */
#[SoapArray(elementName: 'DetailSettings')]
final readonly class DetailSettingsList
{
    /**
     * @param list<DetailSettings> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
