<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Article;

use RuntimeException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The article does not exist in the company.
 */
final class ArticleNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $articleCode,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?ProcessXmlException $previous = null,
    ) {
        parent::__construct(sprintf('Article %s not found: %s', $articleCode, $reason), 0, $previous);
    }

    /**
     * The not-found exception when Twinfield refused a read because the article does not exist, or
     * null for any other error.
     *
     * Twinfield then answers with an empty element and one error on it:
     * `<article msgtype="error" msg="Artikel X bestaat niet." result="0"/>`. The message is in the
     * language of the user, so the structure decides, not the text.
     *
     * @internal
     */
    public static function fromProcessXml(ProcessXmlException $exception, string $code): ?self
    {
        $result = $exception->result;
        $errors = $result->errors();
        $root = XmlElement::root($result);

        $isNotFound = $root->element->localName === 'article'
            && count($errors) === 1
            && $errors[0]->path === 'article'
            && $root->nullableChild('header') === null;

        return $isNotFound ? new self($code, $errors[0]->text, $exception) : null;
    }
}
