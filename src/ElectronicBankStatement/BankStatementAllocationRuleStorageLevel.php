<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

/**
 * Whether and where the allocation of a line is saved as an allocation rule
 * (`BankStatementAllocationRuleStorageLevel`).
 *
 * The documentation refers to this enum but does not define it; it lists a
 * `BankStatementAllocationRuleType` without `NotStored` instead. These are the values of the WSDL.
 */
enum BankStatementAllocationRuleStorageLevel: string
{
    /** Not saved as an allocation rule. */
    case NotStored = 'NotStored';

    /** Saved for the bank book in this company. */
    case LocalBank = 'LocalBank';

    /** Saved for this company. */
    case LocalOffice = 'LocalOffice';

    /** Saved for the bank book in the template company. */
    case TemplateBank = 'TemplateBank';

    /** Saved for the template company. */
    case TemplateOffice = 'TemplateOffice';
}
