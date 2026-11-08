<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Fixed asset settings of the office (`fixedassets`): the transaction type for depreciation
 * and the browses that show the asset transaction lines.
 */
final readonly class FixedAssetSettings
{
    public function __construct(
        /** Transaction type of the depreciation transactions, e.g. `ACTIVA`. */
        public NamedReference $transaction,
        /** Browse for purchases (`browsepurchase`), e.g. `310`. */
        public NamedReference $browsePurchase,
        /** Browse for depreciation (`browsedepreciate`), e.g. `310`. */
        public NamedReference $browseDepreciate,
        /** Browse code for reconciliation (`browsereconcile`), e.g. `310`; Twinfield gives no name. */
        public string $browseReconcile,
    ) {
    }

    public static function fromXml(XmlElement $fixedAssets): self
    {
        return new self(
            transaction: NamedReference::fromXml($fixedAssets->child('transaction')),
            browsePurchase: NamedReference::fromXml($fixedAssets->child('browsepurchase')),
            browseDepreciate: NamedReference::fromXml($fixedAssets->child('browsedepreciate')),
            browseReconcile: $fixedAssets->string('browsereconcile'),
        );
    }
}
