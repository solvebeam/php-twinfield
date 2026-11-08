<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

/**
 * A setting of an office that Twinfield can lock (`@locked`), e.g. the VAT declaration period
 * once declarations were filed. The value is the path of the element within `<office>`.
 *
 *   if ($office->isLocked(OfficeField::DeclarationTimeframe)) { … }
 */
enum OfficeField: string
{
    case Template = 'general/template';
    case TemplateOffice = 'general/templateoffice';
    case Country = 'general/address/country';
    case DeclarationTimeframe = 'vatmanagement/decltimeframe';
    case StartingQuarter = 'vatmanagement/startingquarter';
    case VatRollover = 'vatmanagement/vatrollover';
    case FiscalYearTimeframeStart = 'vatmanagement/fiscalyeartimeframestart';
    case AccountingScheme = 'vatmanagement/accountingscheme';
}
