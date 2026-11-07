<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

/**
 * Performance type for ICT VAT codes (intra-community supplies).
 */
enum PerformanceType: string
{
    case Services = 'services';
    case Goods = 'goods';
}
