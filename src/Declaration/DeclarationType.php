<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration;

/**
 * Kind of return (`DocumentCode`). All five were seen live.
 */
enum DeclarationType: string
{
    /** VAT return (btw-aangifte), per month, quarter or year. */
    case VatTurnover = 'VATTURNOVER';

    /** ICT return (opgaaf intracommunautaire prestaties, ICP). */
    case Ict = 'VATICT';

    /** Yearly supplementary VAT return (suppletie). */
    case YearlySupplementation = 'YEARLYSUPPLETION';

    /** VAT return of a tax group (fiscale eenheid). */
    case TaxGroup = 'TAXGROUP';

    /** Yearly supplementary VAT return of a tax group. */
    case TaxGroupYearlySupplementation = 'TXGRPYRSP';
}
