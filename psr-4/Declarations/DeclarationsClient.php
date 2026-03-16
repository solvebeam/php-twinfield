<?php

namespace SolveBeam\Twinfield\Declarations;

use SolveBeam\Twinfield\Declarations\Type;
use Phpro\SoapClient\Type\ResultInterface;
use Phpro\SoapClient\Exception\SoapException;
use Phpro\SoapClient\Type\RequestInterface;

class DeclarationsClient
{
    private \Phpro\SoapClient\Caller\Caller $caller;

    public function __construct(\Phpro\SoapClient\Caller\Caller $caller)
    {
        $this->caller = $caller;
    }

    /**
     * @param RequestInterface & Type\GetAllSummaries $parameters
     * @return ResultInterface & Type\GetAllSummariesResponse
     * @throws SoapException
     */
    public function getAllSummaries(\SolveBeam\Twinfield\Declarations\Type\GetAllSummaries $parameters): \SolveBeam\Twinfield\Declarations\Type\GetAllSummariesResponse
    {
        $response = ($this->caller)('GetAllSummaries', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetAllSummariesResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetRangeOfSummaries $parameters
     * @return ResultInterface & Type\GetRangeOfSummariesResponse
     * @throws SoapException
     */
    public function getRangeOfSummaries(\SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummaries $parameters): \SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesResponse
    {
        $response = ($this->caller)('GetRangeOfSummaries', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetRangeOfSummariesSinceModified $parameters
     * @return ResultInterface & Type\GetRangeOfSummariesSinceModifiedResponse
     * @throws SoapException
     */
    public function getRangeOfSummariesSinceModified(\SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesSinceModified $parameters): \SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesSinceModifiedResponse
    {
        $response = ($this->caller)('GetRangeOfSummariesSinceModified', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesSinceModifiedResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetRangeOfSummariesByDeclarationYear $parameters
     * @return ResultInterface & Type\GetRangeOfSummariesByDeclarationYearResponse
     * @throws SoapException
     */
    public function getRangeOfSummariesByDeclarationYear(\SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesByDeclarationYear $parameters): \SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesByDeclarationYearResponse
    {
        $response = ($this->caller)('GetRangeOfSummariesByDeclarationYear', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesByDeclarationYearResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetRangeOfSummariesByDeclarationYearAndSinceModified $parameters
     * @return ResultInterface & Type\GetRangeOfSummariesByDeclarationYearAndSinceModifiedResponse
     * @throws SoapException
     */
    public function getRangeOfSummariesByDeclarationYearAndSinceModified(\SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesByDeclarationYearAndSinceModified $parameters): \SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesByDeclarationYearAndSinceModifiedResponse
    {
        $response = ($this->caller)('GetRangeOfSummariesByDeclarationYearAndSinceModified', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetRangeOfSummariesByDeclarationYearAndSinceModifiedResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetNumberOfDeclarations $parameters
     * @return ResultInterface & Type\GetNumberOfDeclarationsResponse
     * @throws SoapException
     */
    public function getNumberOfDeclarations(\SolveBeam\Twinfield\Declarations\Type\GetNumberOfDeclarations $parameters): \SolveBeam\Twinfield\Declarations\Type\GetNumberOfDeclarationsResponse
    {
        $response = ($this->caller)('GetNumberOfDeclarations', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetNumberOfDeclarationsResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetVatReturnAsXbrl $parameters
     * @return ResultInterface & Type\GetVatReturnAsXbrlResponse
     * @throws SoapException
     */
    public function getVatReturnAsXbrl(\SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXbrl $parameters): \SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXbrlResponse
    {
        $response = ($this->caller)('GetVatReturnAsXbrl', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXbrlResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetVatReturnAsXml $parameters
     * @return ResultInterface & Type\GetVatReturnAsXmlResponse
     * @throws SoapException
     */
    public function getVatReturnAsXml(\SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXml $parameters): \SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXmlResponse
    {
        $response = ($this->caller)('GetVatReturnAsXml', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXmlResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetVatReturnPaymentReference $parameters
     * @return ResultInterface & Type\GetVatReturnPaymentReferenceResponse
     * @throws SoapException
     */
    public function getVatReturnPaymentReference(\SolveBeam\Twinfield\Declarations\Type\GetVatReturnPaymentReference $parameters): \SolveBeam\Twinfield\Declarations\Type\GetVatReturnPaymentReferenceResponse
    {
        $response = ($this->caller)('GetVatReturnPaymentReference', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetVatReturnPaymentReferenceResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetIctReturnAsXbrl $parameters
     * @return ResultInterface & Type\GetIctReturnAsXbrlResponse
     * @throws SoapException
     */
    public function getIctReturnAsXbrl(\SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXbrl $parameters): \SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXbrlResponse
    {
        $response = ($this->caller)('GetIctReturnAsXbrl', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXbrlResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetIctReturnAsXml $parameters
     * @return ResultInterface & Type\GetIctReturnAsXmlResponse
     * @throws SoapException
     */
    public function getIctReturnAsXml(\SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXml $parameters): \SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXmlResponse
    {
        $response = ($this->caller)('GetIctReturnAsXml', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXmlResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetTaxGroupVatReturnAsXbrl $parameters
     * @return ResultInterface & Type\GetTaxGroupVatReturnAsXbrlResponse
     * @throws SoapException
     */
    public function getTaxGroupVatReturnAsXbrl(\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrl $parameters): \SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrlResponse
    {
        $response = ($this->caller)('GetTaxGroupVatReturnAsXbrl', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrlResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetTaxGroupVatReturnAsXml $parameters
     * @return ResultInterface & Type\GetTaxGroupVatReturnAsXmlResponse
     * @throws SoapException
     */
    public function getTaxGroupVatReturnAsXml(\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXml $parameters): \SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXmlResponse
    {
        $response = ($this->caller)('GetTaxGroupVatReturnAsXml', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXmlResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\GetTaxGroupVatReturnPaymentReference $parameters
     * @return ResultInterface & Type\GetTaxGroupVatReturnPaymentReferenceResponse
     * @throws SoapException
     */
    public function getTaxGroupVatReturnPaymentReference(\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnPaymentReference $parameters): \SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnPaymentReferenceResponse
    {
        $response = ($this->caller)('GetTaxGroupVatReturnPaymentReference', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnPaymentReferenceResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\SetToSent $parameters
     * @return ResultInterface & Type\SetToSentResponse
     * @throws SoapException
     */
    public function setToSent(\SolveBeam\Twinfield\Declarations\Type\SetToSent $parameters): \SolveBeam\Twinfield\Declarations\Type\SetToSentResponse
    {
        $response = ($this->caller)('SetToSent', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\SetToSentResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\SetToApproved $parameters
     * @return ResultInterface & Type\SetToApprovedResponse
     * @throws SoapException
     */
    public function setToApproved(\SolveBeam\Twinfield\Declarations\Type\SetToApproved $parameters): \SolveBeam\Twinfield\Declarations\Type\SetToApprovedResponse
    {
        $response = ($this->caller)('SetToApproved', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\SetToApprovedResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }

    /**
     * @param RequestInterface & Type\SetToRejected $parameters
     * @return ResultInterface & Type\SetToRejectedResponse
     * @throws SoapException
     */
    public function setToRejected(\SolveBeam\Twinfield\Declarations\Type\SetToRejected $parameters): \SolveBeam\Twinfield\Declarations\Type\SetToRejectedResponse
    {
        $response = ($this->caller)('SetToRejected', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Declarations\Type\SetToRejectedResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }
}

