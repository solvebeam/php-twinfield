<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use RuntimeException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The dimension does not exist in the company, e.g. activity `A999`.
 *
 * Also thrown for a code that does not match the code format of the dimension type
 * (such as `A[0-9][0-9][0-9]`), because such a dimension cannot exist.
 */
final class DimensionNotFoundException extends RuntimeException
{
    public function __construct(
        /** Dimension type, e.g. `ACT`. */
        public readonly string $dimensionType,
        public readonly string $dimensionCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?ProcessXmlException $previous = null,
    ) {
        parent::__construct(
            sprintf('Dimension %s %s not found: %s', $dimensionType, $dimensionCode, $reason),
            0,
            $previous,
        );
    }

    /**
     * The not-found exception when Twinfield refused a request because the dimension does
     * not exist, or null for any other error.
     *
     * Twinfield then answers with an empty dimension (no `uid`) and one error on `code`,
     * e.g. `<code msgtype="error" msg="Dimensie A999 bestaat niet.">A999</code>`.
     * The message is in the language of the user, so the structure decides, not the text.
     */
    public static function fromProcessXml(ProcessXmlException $exception, string $type, string $code): ?self
    {
        $result = $exception->result;
        $errors = $result->errors();
        $root = XmlElement::root($result);

        $isNotFound = $root->element->localName === 'dimension'
            && count($errors) === 1
            && $errors[0]->path === 'dimension/code'
            && $root->nullableChild('uid') === null;

        return $isNotFound ? new self($type, $code, $errors[0]->text, $exception) : null;
    }
}
