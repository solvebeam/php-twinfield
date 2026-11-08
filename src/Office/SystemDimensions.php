<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The dimensions the office uses for system purposes (`systemdimensions`), such as the control
 * accounts for customers and suppliers.
 *
 *   $receivable = $office->systemDimensions->accountsReceivable?->code;   // e.g. 13000
 *
 * Each one is null when not set; not every office sets all of them.
 */
final readonly class SystemDimensions
{
    public function __construct(
        /** Control account for customers (`accountsreceivable`). */
        public ?SystemDimension $accountsReceivable,
        /** Control account for suppliers (`accountspayable`). */
        public ?SystemDimension $accountsPayable,
        /** Suspense account (`suspenceaccount`), for amounts that are not yet sorted out. */
        public ?SystemDimension $suspenseAccount,
        /** Work in progress (`workingprogress`). */
        public ?SystemDimension $workInProgress,
        /** Profit taking on projects (`profitriseprojects`). */
        public ?SystemDimension $profitRiseProjects,
        /** Default turnover account. */
        public ?SystemDimension $turnover,
        public DebitCredit $exchangeDifference,
        public DebitCredit $paymentDifference,
        /** Payment discount accounts. */
        public DebitCredit $discount,
        /** Cost center for `teqcostcenter`; not documented by Twinfield. */
        public ?SystemDimension $teqCostCenter,
        /** Default cost center of fixed assets (`astcostcenter`). */
        public ?SystemDimension $assetCostCenter,
        /** Account for the result of the year in the opening balance (`openingbalance`). */
        public ?SystemDimension $openingBalance,
        /** Contra account for closing the profit and loss (`closepnl`). */
        public ?SystemDimension $closeProfitAndLoss,
        /** `forbalynd`; not documented by Twinfield. */
        public ?SystemDimension $forBalYnd,
    ) {
    }

    public static function fromXml(XmlElement $dimensions): self
    {
        return new self(
            accountsReceivable: SystemDimension::nullable($dimensions->child('accountsreceivable')),
            accountsPayable: SystemDimension::nullable($dimensions->child('accountspayable')),
            suspenseAccount: SystemDimension::nullable($dimensions->child('suspenceaccount')),
            workInProgress: SystemDimension::nullable($dimensions->child('workingprogress')),
            profitRiseProjects: SystemDimension::nullable($dimensions->child('profitriseprojects')),
            turnover: SystemDimension::nullable($dimensions->child('turnover')),
            exchangeDifference: DebitCredit::fromXml($dimensions->child('exchangedifference')),
            paymentDifference: DebitCredit::fromXml($dimensions->child('paymentdifference')),
            discount: DebitCredit::fromXml($dimensions->child('discount')),
            teqCostCenter: SystemDimension::nullable($dimensions->child('teqcostcenter')),
            assetCostCenter: SystemDimension::nullable($dimensions->child('astcostcenter')),
            openingBalance: SystemDimension::nullable($dimensions->child('openingbalance')),
            closeProfitAndLoss: SystemDimension::nullable($dimensions->child('closepnl')),
            forBalYnd: SystemDimension::nullable($dimensions->child('forbalynd')),
        );
    }
}
