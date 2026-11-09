<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

/**
 * Status of a VAT code (`vat/@status`) or of one of its percentages (`percentage/@status`).
 */
enum VatCodeStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';

    /** Twinfield hides a VAT code instead of deleting it when it is in use. */
    case Hidden = 'hide';
}
