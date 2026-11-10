<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use RuntimeException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The user does not exist in the organisation.
 */
final class UserNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $userCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?ProcessXmlException $previous = null,
    ) {
        parent::__construct(sprintf('User %s not found: %s', $userCode, $reason), 0, $previous);
    }

    /**
     * The not-found exception when Twinfield refused a request because the user does not exist,
     * or null for any other error.
     *
     * Twinfield then answers with only the code, and one error on the root element:
     * `<user iscurrentuser="false" level="Supervisor" status="default" msgtype="error"
     * msg="Gebruiker JDOE bestaat niet." result="0"><code>JDOE</code></user>`. The message is in
     * the language of the user, so the structure decides, not the text.
     *
     * @internal
     */
    public static function fromProcessXml(ProcessXmlException $exception, string $code): ?self
    {
        $result = $exception->result;
        $errors = $result->errors();
        $root = XmlElement::root($result);

        $isNotFound = $root->element->localName === 'user'
            && count($errors) === 1
            && $errors[0]->path === 'user'
            && $root->nullableChild('name') === null;

        return $isNotFound ? new self($code, $errors[0]->text, $exception) : null;
    }
}
