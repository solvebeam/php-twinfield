<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of Unbind (hierarchies.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('Unbind')]
#[SoapAction(action: 'http://www.twinfield.com/Unbind')]
final readonly class Unbind
{
    public function __construct(
        /** ID of the node. */
        #[SoapName('node')]
        public int $node,
        /** Company of the accounts. */
        #[SoapName('office')]
        public string $office,
        #[SoapName('accounts')]
        public AccountList $accounts,
    ) {
    }
}
