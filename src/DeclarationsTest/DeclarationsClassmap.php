<?php

namespace SolveBeam\Twinfield\Declarations;

use SolveBeam\Twinfield\Declarations\Type;
use Soap\Encoding\ClassMap\ClassMapCollection;
use Soap\Encoding\ClassMap\ClassMap;

class DeclarationsClassmap
{
    public static function types(): \Soap\Encoding\ClassMap\ClassMapCollection
    {
        return new ClassMapCollection(
            new ClassMap('http://www.twinfield.com/', 'ArrayOfMessageOfLoadMessage', Type\ArrayOfMessageOfLoadMessage::class),
            new ClassMap('http://www.twinfield.com/', 'MessageOfLoadMessage', Type\MessageOfLoadMessage::class),
            new ClassMap('http://www.twinfield.com/', 'ArrayOfString', Type\ArrayOfString::class),
            new ClassMap('http://www.twinfield.com/', 'ArrayOfDeclarationSummary', Type\ArrayOfDeclarationSummary::class),
            new ClassMap('http://www.twinfield.com/', 'DeclarationSummary', Type\DeclarationSummary::class),
            new ClassMap('http://www.twinfield.com/', 'DocumentTimeFrame', Type\DocumentTimeFrame::class),
            new ClassMap('http://www.twinfield.com/', 'DocumentStatus', Type\DocumentStatus::class),
            new ClassMap('http://www.twinfield.com/', 'CodeName', Type\CodeName::class),
            new ClassMap('http://www.twinfield.com/', 'Header', Type\Header::class),
            new ClassMap('http://www.twinfield.com/', 'ArrayOfMessageOfSetStatusMessages', Type\ArrayOfMessageOfSetStatusMessages::class),
            new ClassMap('http://www.twinfield.com/', 'MessageOfSetStatusMessages', Type\MessageOfSetStatusMessages::class),
            new ClassMap('http://www.twinfield.com/', 'GetAllSummaries', Type\GetAllSummaries::class),
            new ClassMap('http://www.twinfield.com/', 'GetAllSummariesResponse', Type\GetAllSummariesResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetRangeOfSummaries', Type\GetRangeOfSummaries::class),
            new ClassMap('http://www.twinfield.com/', 'GetRangeOfSummariesResponse', Type\GetRangeOfSummariesResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetRangeOfSummariesSinceModified', Type\GetRangeOfSummariesSinceModified::class),
            new ClassMap('http://www.twinfield.com/', 'GetRangeOfSummariesSinceModifiedResponse', Type\GetRangeOfSummariesSinceModifiedResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetRangeOfSummariesByDeclarationYear', Type\GetRangeOfSummariesByDeclarationYear::class),
            new ClassMap('http://www.twinfield.com/', 'GetRangeOfSummariesByDeclarationYearResponse', Type\GetRangeOfSummariesByDeclarationYearResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetRangeOfSummariesByDeclarationYearAndSinceModified', Type\GetRangeOfSummariesByDeclarationYearAndSinceModified::class),
            new ClassMap('http://www.twinfield.com/', 'GetRangeOfSummariesByDeclarationYearAndSinceModifiedResponse', Type\GetRangeOfSummariesByDeclarationYearAndSinceModifiedResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetNumberOfDeclarations', Type\GetNumberOfDeclarations::class),
            new ClassMap('http://www.twinfield.com/', 'GetNumberOfDeclarationsResponse', Type\GetNumberOfDeclarationsResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetVatReturnAsXbrl', Type\GetVatReturnAsXbrl::class),
            new ClassMap('http://www.twinfield.com/', 'GetVatReturnAsXbrlResponse', Type\GetVatReturnAsXbrlResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetVatReturnAsXbrlResponseVatReturn', Type\GetVatReturnAsXbrlResponseVatReturn::class),
            new ClassMap('http://www.twinfield.com/', 'GetVatReturnAsXml', Type\GetVatReturnAsXml::class),
            new ClassMap('http://www.twinfield.com/', 'GetVatReturnAsXmlResponse', Type\GetVatReturnAsXmlResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetVatReturnAsXmlResponseVatReturn', Type\GetVatReturnAsXmlResponseVatReturn::class),
            new ClassMap('http://www.twinfield.com/', 'GetVatReturnPaymentReference', Type\GetVatReturnPaymentReference::class),
            new ClassMap('http://www.twinfield.com/', 'GetVatReturnPaymentReferenceResponse', Type\GetVatReturnPaymentReferenceResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetIctReturnAsXbrl', Type\GetIctReturnAsXbrl::class),
            new ClassMap('http://www.twinfield.com/', 'GetIctReturnAsXbrlResponse', Type\GetIctReturnAsXbrlResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetIctReturnAsXbrlResponseVatReturn', Type\GetIctReturnAsXbrlResponseVatReturn::class),
            new ClassMap('http://www.twinfield.com/', 'GetIctReturnAsXml', Type\GetIctReturnAsXml::class),
            new ClassMap('http://www.twinfield.com/', 'GetIctReturnAsXmlResponse', Type\GetIctReturnAsXmlResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetIctReturnAsXmlResponseVatReturn', Type\GetIctReturnAsXmlResponseVatReturn::class),
            new ClassMap('http://www.twinfield.com/', 'GetTaxGroupVatReturnAsXbrl', Type\GetTaxGroupVatReturnAsXbrl::class),
            new ClassMap('http://www.twinfield.com/', 'GetTaxGroupVatReturnAsXbrlResponse', Type\GetTaxGroupVatReturnAsXbrlResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetTaxGroupVatReturnAsXbrlResponseVatReturn', Type\GetTaxGroupVatReturnAsXbrlResponseVatReturn::class),
            new ClassMap('http://www.twinfield.com/', 'GetTaxGroupVatReturnAsXml', Type\GetTaxGroupVatReturnAsXml::class),
            new ClassMap('http://www.twinfield.com/', 'GetTaxGroupVatReturnAsXmlResponse', Type\GetTaxGroupVatReturnAsXmlResponse::class),
            new ClassMap('http://www.twinfield.com/', 'GetTaxGroupVatReturnAsXmlResponseVatReturn', Type\GetTaxGroupVatReturnAsXmlResponseVatReturn::class),
            new ClassMap('http://www.twinfield.com/', 'GetTaxGroupVatReturnPaymentReference', Type\GetTaxGroupVatReturnPaymentReference::class),
            new ClassMap('http://www.twinfield.com/', 'GetTaxGroupVatReturnPaymentReferenceResponse', Type\GetTaxGroupVatReturnPaymentReferenceResponse::class),
            new ClassMap('http://www.twinfield.com/', 'SetToSent', Type\SetToSent::class),
            new ClassMap('http://www.twinfield.com/', 'SetToSentResponse', Type\SetToSentResponse::class),
            new ClassMap('http://www.twinfield.com/', 'SetToApproved', Type\SetToApproved::class),
            new ClassMap('http://www.twinfield.com/', 'SetToApprovedResponse', Type\SetToApprovedResponse::class),
            new ClassMap('http://www.twinfield.com/', 'SetToRejected', Type\SetToRejected::class),
            new ClassMap('http://www.twinfield.com/', 'SetToRejectedResponse', Type\SetToRejectedResponse::class),
        );
    }

    public static function enums(): \Soap\Encoding\ClassMap\ClassMapCollection
    {
        return new ClassMapCollection(
            new ClassMap('http://www.twinfield.com/', 'MessageType', Type\MessageType::class),
            new ClassMap('http://www.twinfield.com/', 'LoadMessage', Type\LoadMessage::class),
            new ClassMap('http://www.twinfield.com/', 'SetStatusMessages', Type\SetStatusMessages::class),
        );
    }
}
