<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of UndoMatch (matching.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('UndoMatch')]
#[SoapAction(action: 'http://www.twinfield.com/UndoMatch')]
final readonly class UndoMatch
{
    public function __construct(
        #[SoapName('office')]
        public string $office,
        #[SoapName('dimensionType')]
        public string $dimensionType,
        #[SoapName('dimension')]
        public string $dimension,
        #[SoapName('matchNumber')]
        public int $matchNumber,
    ) {
    }
}
