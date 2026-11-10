<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use RuntimeException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use Throwable;

/**
 * The office does not exist, or the user has no access to it.
 */
final class OfficeNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $officeCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Office %s not found: %s', $officeCode, $reason), 0, $previous);
    }

    /**
     * The not-found exception when Twinfield refused the read because the office does not
     * exist, or null for any other error.
     *
     * Twinfield then answers with only the code and one error on the root element:
     * `<office msgtype="error" msg="Administratie (9999) bestaat niet." result="0"><code>9999</code></office>`.
     * The message is in the language of the user, so the structure decides, not the text.
     *
     * @internal
     */
    public static function fromProcessXml(ProcessXmlException $exception, string $code): ?self
    {
        $result = $exception->result;
        $errors = $result->errors();
        $root = XmlElement::root($result);

        $isNotFound = $root->element->localName === 'office'
            && count($errors) === 1
            && $errors[0]->path === 'office'
            && $root->nullableChild('name') === null;

        return $isNotFound ? new self($code, $errors[0]->text, $exception) : null;
    }
}
