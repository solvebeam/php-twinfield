<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The dimension types the office uses for each purpose (`systemdimensiontypes`), with their
 * names in the language of the user.
 *
 *   echo $office->systemDimensionTypes->accountsReceivable->code;   // DEB
 */
final readonly class SystemDimensionTypes
{
    public function __construct(
        /** Customers, e.g. `DEB`. */
        public NamedReference $accountsReceivable,
        /** Suppliers, e.g. `CRD`. */
        public NamedReference $accountsPayable,
        /** Balance sheet accounts, e.g. `BAS`. */
        public NamedReference $balance,
        /** Profit and loss accounts, e.g. `PNL`. */
        public NamedReference $profitAndLoss,
        /** Fixed assets, e.g. `AST`. */
        public NamedReference $fixedAssets,
        /** Projects, e.g. `PRJ`. */
        public NamedReference $projects,
        /** Cost centers, e.g. `KPL`. */
        public NamedReference $costCenters,
        /** Activities, e.g. `ACT`. */
        public NamedReference $activities,
    ) {
    }

    public static function fromXml(XmlElement $types): self
    {
        return new self(
            accountsReceivable: NamedReference::fromXml($types->child('accountsreceivable')),
            accountsPayable: NamedReference::fromXml($types->child('accountspayable')),
            balance: NamedReference::fromXml($types->child('balance')),
            profitAndLoss: NamedReference::fromXml($types->child('profitandloss')),
            fixedAssets: NamedReference::fromXml($types->child('fixedassets')),
            projects: NamedReference::fromXml($types->child('projects')),
            costCenters: NamedReference::fromXml($types->child('costcenters')),
            activities: NamedReference::fromXml($types->child('activities')),
        );
    }
}
