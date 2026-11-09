<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use RuntimeException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The VAT code does not exist in the company.
 */
final class VatCodeNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $vatCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?ProcessXmlException $previous = null,
    ) {
        parent::__construct(sprintf('VAT code %s not found: %s', $vatCode, $reason), 0, $previous);
    }

    /**
     * The not-found exception when Twinfield refused a request because the VAT code does not
     * exist, or null for any other error.
     *
     * Twinfield then answers with only the code, and one error on the root element:
     * `<vat msgtype="error" msg="Btw-code VX bestaat niet." result="0"><code>VX</code></vat>`.
     * The message is in the language of the user, so the structure decides, not the text.
     */
    public static function fromProcessXml(ProcessXmlException $exception, string $code): ?self
    {
        $result = $exception->result;
        $errors = $result->errors();
        $root = XmlElement::root($result);

        $isNotFound = $root->element->localName === 'vat'
            && count($errors) === 1
            && $errors[0]->path === 'vat'
            && $root->nullableChild('name') === null;

        return $isNotFound ? new self($code, $errors[0]->text, $exception) : null;
    }
}
