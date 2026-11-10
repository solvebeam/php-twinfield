<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Soap\SoapName;

/**
 * `SummarySettings`: the changeable values of a summary. Relation, currency and ID are the key.
 * Fields in WSDL order.
 *
 * @internal
 */
final readonly class SummarySettings
{
    public function __construct(
        #[SoapName('Relation')]
        public string $relation,
        #[SoapName('Currency')]
        public string $currency,
        #[SoapName('Id')]
        public int $id,
        /** `true` or `false`. */
        #[SoapName('Included')]
        public string $included,
        #[SoapName('Details')]
        public DetailSettingsList $details,
    ) {
    }
}
