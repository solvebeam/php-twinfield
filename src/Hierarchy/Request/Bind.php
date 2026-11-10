<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of Bind (hierarchies.asmx). Fields in WSDL order.
 *
 * @internal
 */
#[SoapName('Bind')]
#[SoapAction(action: 'http://www.twinfield.com/Bind')]
final readonly class Bind
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
