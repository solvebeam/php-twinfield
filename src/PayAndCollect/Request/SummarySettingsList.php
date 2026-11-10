<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapArray;

/**
 * `ArrayOfSummarySettings`.
 *
 * @internal
 */
#[SoapArray(elementName: 'SummarySettings')]
final readonly class SummarySettingsList
{
    /**
     * @param list<SummarySettings> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
