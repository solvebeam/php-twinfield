<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ProcessXml;

use DOMDocument;
use DOMElement;
use DOMXPath;
use SolveBeam\Twinfield\Soap\SoapResponse;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Response of a ProcessXml request.
 *
 * Twinfield adds attributes to the elements of the response: `result` (`1` or `0`),
 * `msg` and `msgtype` for errors and warnings, and `name`, `shortname` and `type`
 * on elements with the code of another entity, e.g.
 * `<currency name="Euro" shortname="€">EUR</currency>`.
 *
 * Read the response with DOM via `root()`, `document()` or `xpath()`, or use `xml()`
 * with another parser, e.g. `simplexml_load_string()` or `XMLReader::XML()`.
 */
final readonly class ProcessXmlResult
{
    private function __construct(
        private string $xml,
        private DOMDocument $document,
        private bool $successful,
    ) {
    }

    public static function fromSoapResponse(SoapResponse $response): self
    {
        $payload = $response->payloadElement();
        $result = null;

        foreach ($payload === null ? [] : $payload->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'ProcessXmlStringResult') {
                $result = $child;
            }
        }

        if ($result === null) {
            throw new UnexpectedResponseException('ProcessXmlStringResponse/ProcessXmlStringResult is missing.');
        }

        return self::fromXml($result->textContent);
    }

    public static function fromXml(string $xml): self
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = trim($xml) !== '' && $document->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;

        if (!$loaded || !$root instanceof DOMElement) {
            throw new UnexpectedResponseException('ProcessXmlStringResult does not contain valid XML.');
        }

        $successful = match ($root->getAttribute('result')) {
            '1' => true,
            '0' => false,
            default => throw new UnexpectedResponseException(sprintf(
                'The <%s> response has no valid result attribute: "%s".',
                $root->localName,
                $root->getAttribute('result'),
            )),
        };

        return new self($xml, $document, $successful);
    }

    public function successful(): bool
    {
        return $this->successful;
    }

    /**
     * The response as Twinfield returned it (the content of `ProcessXmlStringResult`).
     */
    public function xml(): string
    {
        return $this->xml;
    }

    public function document(): DOMDocument
    {
        return $this->document;
    }

    public function root(): DOMElement
    {
        $root = $this->document->documentElement;

        // Checked in fromXml().
        assert($root instanceof DOMElement);

        return $root;
    }

    public function xpath(): DOMXPath
    {
        return new DOMXPath($this->document);
    }

    /**
     * All errors and warnings, in document order.
     *
     * @return list<ProcessXmlMessage>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->document->getElementsByTagName('*') as $element) {
            if (!$element->hasAttribute('msg')) {
                continue;
            }

            $path = self::path($element);
            $type = MessageType::tryFrom($element->getAttribute('msgtype'));

            if ($type === null) {
                throw new UnexpectedResponseException(sprintf(
                    '%s has a message with an unknown msgtype "%s": %s',
                    $path,
                    $element->getAttribute('msgtype'),
                    $element->getAttribute('msg'),
                ));
            }

            $messages[] = new ProcessXmlMessage($type, $element->getAttribute('msg'), $path, $element);
        }

        return $messages;
    }

    /**
     * @return list<ProcessXmlMessage>
     */
    public function errors(): array
    {
        return $this->messagesOfType(MessageType::Error);
    }

    /**
     * @return list<ProcessXmlMessage>
     */
    public function warnings(): array
    {
        return $this->messagesOfType(MessageType::Warning);
    }

    /**
     * @throws ProcessXmlException When the root element has `result="0"`.
     */
    public function throwIfFailed(): self
    {
        if (!$this->successful) {
            throw new ProcessXmlException($this);
        }

        return $this;
    }

    /**
     * @return list<ProcessXmlMessage>
     */
    private function messagesOfType(MessageType $type): array
    {
        return array_values(array_filter(
            $this->messages(),
            static fn (ProcessXmlMessage $message): bool => $message->type === $type,
        ));
    }

    /**
     * E.g. `transactions/transaction[2]/header/period`, with an index only when the name occurs more than once.
     */
    private static function path(DOMElement $element): string
    {
        $parts = [];

        for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
            $name = $node->localName;
            $index = 0;
            $count = 0;

            foreach ($node->parentNode instanceof DOMElement ? $node->parentNode->childNodes : [] as $sibling) {
                if ($sibling instanceof DOMElement && $sibling->localName === $name) {
                    $count++;

                    if ($sibling === $node) {
                        $index = $count;
                    }
                }
            }

            $parts[] = $count > 1 ? sprintf('%s[%d]', $name, $index) : $name;
        }

        return implode('/', array_reverse($parts));
    }
}
