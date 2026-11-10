<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration\Request;

use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * Body of GetIctReturnAsXbrl (declarations.asmx). Fields in WSDL order.
 *
 * The boolean is sent as `true`/`false`: SoapEnvelope casts scalars with (string).
 *
 * @internal
 */
#[SoapName('GetIctReturnAsXbrl')]
#[SoapAction(action: 'http://www.twinfield.com/GetIctReturnAsXbrl')]
final readonly class GetIctReturnAsXbrl
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
