<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionType;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Levels of a dimension type (read-only). A transaction line has a dimension per level, e.g.
 * a sales line: general ledger account (level 1), customer (2) and project (3).
 *
 * 0 means the type is not used at that level.
 */
final readonly class DimensionTypeLevels
{
    public function __construct(
        /** Financial level: 1 general ledger (BAS, PNL), 2 e.g. DEB, CRD, KPL, 3 AST, PRJ, 4 ACT. */
        public int $financial,
        /** Level in time & expenses, e.g. 1 for projects and 2 for activities. */
        public int $time,
        /** Level in fixed assets, e.g. 1 for assets (AST). */
        public int $fixedAssets,
        /** Level in invoices; not in use according to Twinfield. */
        public int $invoices,
    ) {
        $levels = ['financial' => $financial, 'time' => $time, 'fixed assets' => $fixedAssets, 'invoices' => $invoices];

        foreach ($levels as $name => $level) {
            if ($level < 0) {
                throw new InvalidArgumentException(sprintf(
                    'Dimension type %s level must be 0 or more, %d given.',
                    $name,
                    $level,
                ));
            }
        }
    }

    /**
     * From the `<levels>` element of a dimension type.
     */
    public static function fromXml(XmlElement $levels): self
    {
        return new self(
            financial: $levels->int('financials'),
            time: $levels->int('time'),
            fixedAssets: $levels->int('fixedassets'),
            invoices: $levels->int('invoices'),
        );
    }
}
