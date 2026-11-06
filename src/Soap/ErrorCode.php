<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

/**
 * General error codes of the classic web services (.asmx), in ClassicServiceFaultException::$errorCode.
 *
 *   try {
 *       $twinfield->offices()->list();
 *   } catch (ClassicServiceFaultException $exception) {
 *       $code = ErrorCode::tryFrom($exception->errorCode);
 *
 *       if ($code === ErrorCode::TokenInvalid) { … }
 *   }
 *
 * Command & Query services use their own codes, e.g. PeriodServiceFaultCode.
 *
 * @see https://developers.twinfield.com/documentation/api/types/guidelines
 */
enum ErrorCode: string
{
    case UnexpectedException = '100';
    /** Access token and company ID (or obsolete session ID) are missing. */
    case AuthenticationMissing = '101';
    case AccessDenied = '102';
    case CredentialsNoLongerValid = '103';
    case LogonDeleted = '104';
    case LogonDisabled = '105';
    case OrganisationInactive = '106';
    case SmsFailed = '107';
    case ClusterInvalid = '108';
    case NoAccessToCompany = '109';
    case LoginNotAllowedOnServer = '110';
    case ClientSoftwareNotAllowed = '122';
    /** Another user logged on with the same user name. */
    case LoggedOff = '168';
    case InvalidZlibData = '186';
    case InvalidXmlData = '187';
    /** The `twf.organisationUserCode` claim is missing; check the scopes. */
    case OrganisationUserCodeClaimMissing = '351';
    /** The `twf.organisationCode` claim is missing; check the scopes. */
    case OrganisationCodeClaimMissing = '352';
    /** The `twf.organisationId` claim is missing; check the scopes. */
    case OrganisationIdClaimMissing = '353';
    case TokenInvalid = '354';
    case TokenNotValidated = '358';
    case CompanyIdMissing = '359';
    /** Access token and session ID are both in the header. */
    case AccessTokenAndSessionId = '360';
    /** Company ID and company code are both in the header. */
    case CompanyIdAndCompanyCode = '361';
    case CompanyNotSupported = '362';
    /** Twinfield documents 383 and 384 with the same description. */
    case PaymentFormatNotSupported = '383';
    case PaymentFormatNotSupported384 = '384';
}
