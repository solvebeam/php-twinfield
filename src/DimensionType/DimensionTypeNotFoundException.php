<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionType;

use RuntimeException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The dimension type does not exist in the company.
 */
final class DimensionTypeNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $dimensionTypeCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?ProcessXmlException $previous = null,
    ) {
        parent::__construct(sprintf('Dimension type %s not found: %s', $dimensionTypeCode, $reason), 0, $previous);
    }

    /**
     * The not-found exception when Twinfield refused a request because the dimension type
     * does not exist, or null for any other error.
     *
     * Twinfield then answers with a placeholder type and one error on the root element:
     * `<dimensiontype msgtype="error" msg="Dimensietype XYZ bestaat niet." result="0">`, with
     * the code as name and every level 0. Every existing type has a financial level, so the
     * structure decides, not the message (which is in the language of the user).
     */
    public static function fromProcessXml(ProcessXmlException $exception, string $code): ?self
    {
        $result = $exception->result;
        $errors = $result->errors();
        $root = XmlElement::root($result);
        $financialLevel = $root->nullableChild('levels')?->nullableChild('financials')?->text();

        $isNotFound = $root->element->localName === 'dimensiontype'
            && count($errors) === 1
            && $errors[0]->path === 'dimensiontype'
            && $financialLevel === '0';

        return $isNotFound ? new self($code, $errors[0]->text, $exception) : null;
    }
}
