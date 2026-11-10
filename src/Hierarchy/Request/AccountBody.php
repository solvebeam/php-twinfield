<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapName;

/**
 * `HierarchyAccount` in a Save, Bind or Unbind request. Fields in WSDL order.
 *
 * @internal
 */
final readonly class AccountBody
{
    public function __construct(
        #[SoapName('Type')]
        public string $type,
        #[SoapName('Code')]
        public string $code,
        #[SoapName('BalanceType')]
        public string $balanceType,
    ) {
    }
}
