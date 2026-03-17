<?php

namespace SolveBeam\Twinfield\Finder\Type;

enum ErrorCodes: string {
    case NoAccessToOffice = 'NoAccessToOffice';
    case OptionNotAllowed = 'OptionNotAllowed';
    case InvalidBooleanOptionValue = 'InvalidBooleanOptionValue';
    case InvalidIntegerOptionValue = 'InvalidIntegerOptionValue';
    case InvalidDecimalOptionValue = 'InvalidDecimalOptionValue';
    case InvalidEnumerationOptionValue = 'InvalidEnumerationOptionValue';
    case OptionValueOutOfRange = 'OptionValueOutOfRange';
    case ParameterOutOfRange = 'ParameterOutOfRange';
    case InvalidFinderType = 'InvalidFinderType';
    case ParameterTooSmall = 'ParameterTooSmall';
    case OptionLevelMandatoryForSectionTeq = 'OptionLevelMandatoryForSectionTeq';
    case OptionIcIncompatibleWithOptionHidden = 'OptionIcIncompatibleWithOptionHidden';
    case InvalidDateTimeOptionLength = 'InvalidDateTimeOptionLength';
    case InvalidDateTimeOptionValue = 'InvalidDateTimeOptionValue';
    case InvalidDateTimeOptionOutOfRange = 'InvalidDateTimeOptionOutOfRange';
    case OptionMandatory = 'OptionMandatory';
    case AccessDenied = 'AccessDenied';
    case DisableAccessRulesNotAllowed = 'DisableAccessRulesNotAllowed';
    case Option1MandatoryIfOption2IsUsed = 'Option1MandatoryIfOption2IsUsed';
    case OptionAvailableForAbnAmroSignUpOnlyIsIncompatibleWithOfficeWildCard = 'OptionAvailableForAbnAmroSignUpOnlyIsIncompatibleWithOfficeWildCard';
    case OptionCompanyAndOptionOfficeCannotBeBothProvided = 'OptionCompanyAndOptionOfficeCannotBeBothProvided';
}
