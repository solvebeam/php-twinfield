<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapName;

/**
 * `DetailSettings`: the changeable values of a detail. Fields in WSDL order.
 *
 * @internal
 */
final readonly class DetailSettings
{
    public function __construct(
        #[SoapName('Id')]
        public int $id,
        /** `xs:decimal`, e.g. `121.00`. */
        #[SoapName('Value')]
        public string $value,
        /** `true` or `false`. */
        #[SoapName('Included')]
        public string $included,
        /** `xs:decimal`; `minOccurs="0"`. */
        #[SoapName('DiscountAmount')]
        public string $discountAmount,
    ) {
    }
}
