<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rate;

use RuntimeException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The rate does not exist in the company.
 */
final class RateNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $rateCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?ProcessXmlException $previous = null,
    ) {
        parent::__construct(sprintf('Rate %s not found: %s', $rateCode, $reason), 0, $previous);
    }

    /**
     * The not-found exception when Twinfield refused a request because the rate does not
     * exist, or null for any other error.
     *
     * Twinfield then answers with only office and code, and one error on the root element:
     * `<projectrate msgtype="error" msg="Tarief T999 bestaat niet." result="0" inuse="false">`
     * with `<office>` and `<code>`. The message is in the language of the user, so the
     * structure decides, not the text.
     *
     * @internal
     */
    public static function fromProcessXml(ProcessXmlException $exception, string $code): ?self
    {
        $result = $exception->result;
        $errors = $result->errors();
        $root = XmlElement::root($result);

        $isNotFound = $root->element->localName === 'projectrate'
            && count($errors) === 1
            && $errors[0]->path === 'projectrate'
            && $root->nullableChild('name') === null;

        return $isNotFound ? new self($code, $errors[0]->text, $exception) : null;
    }
}
