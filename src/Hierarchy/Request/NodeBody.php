<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapName;

/**
 * `HierarchyNode` in a Save request. Fields in WSDL order; `Messages` is left out.
 *
 * @internal
 */
final readonly class NodeBody
{
    public function __construct(
        /** Null (left out) for a new node. */
        #[SoapName('Id')]
        public ?int $id,
        #[SoapName('Code')]
        public string $code,
        #[SoapName('Name')]
        public string $name,
        #[SoapName('Description')]
        public ?string $description,
        #[SoapName('Accounts')]
        public AccountList $accounts,
        #[SoapName('ChildNodes')]
        public NodeList $childNodes,
        #[SoapName('Touched')]
        public int $touched,
    ) {
    }
}
