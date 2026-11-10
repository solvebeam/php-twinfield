<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Soap\SoapName;

/**
 * `Hierarchy` in a Save request. Fields in WSDL order.
 *
 * @internal
 */
final readonly class HierarchyBody
{
    public function __construct(
        #[SoapName('Code')]
        public string $code,
        #[SoapName('Name')]
        public string $name,
        #[SoapName('Description')]
        public ?string $description,
        #[SoapName('RootNode')]
        public NodeBody $rootNode,
        #[SoapName('AccessRights')]
        public AccessList $accessRights,
        #[SoapName('Touched')]
        public int $touched,
    ) {
    }
}
