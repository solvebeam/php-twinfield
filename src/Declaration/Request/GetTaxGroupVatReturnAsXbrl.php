<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetTaxGroupVatReturnAsXbrl (declarations.asmx). Fields in WSDL order.
 *
 * The boolean is sent as `true`/`false`: SoapEnvelope casts scalars with (string).
 *
 * @internal
 */
#[SoapName('GetTaxGroupVatReturnAsXbrl')]
#[SoapAction(action: 'http://www.twinfield.com/GetTaxGroupVatReturnAsXbrl')]
final readonly class GetTaxGroupVatReturnAsXbrl
{
    #[SoapName('documentId')]
    public int $documentId;

    #[SoapName('isMessageIdRequired')]
    public string $isMessageIdRequired;

    public function __construct(
        int $documentId,
        bool $isMessageIdRequired,
    ) {
        $this->documentId = $documentId;
        $this->isMessageIdRequired = $isMessageIdRequired ? 'true' : 'false';
    }
}
