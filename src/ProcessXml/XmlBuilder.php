<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ProcessXml;

use DOMDocument;
use DOMElement;

/**
 * Builds a ProcessXml request with DOM, so values are always escaped.
 *
 *   $xml = XmlBuilder::create('read');
 *   $xml->add($xml->root(), 'type', 'dimensions');
 *   $xml->add($xml->root(), 'code', 'A&B');   // <code>A&amp;B</code>
 *
 *   $twinfield->processXml($xml->toXml());
 */
final readonly class XmlBuilder
{
    private function __construct(
        private DOMDocument $document,
        private DOMElement $root,
    ) {
    }

    /**
     * @param array<string, string> $attributes
     */
    public static function create(string $root, array $attributes = []): self
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $element = $document->createElement($root);
        $document->appendChild($element);

        self::setAttributes($element, $attributes);

        return new self($document, $element);
    }

    public function root(): DOMElement
    {
        return $this->root;
    }

    /**
     * Adds `<name>value</name>` to the parent; null gives an empty element `<name/>`.
     *
     * @param array<string, string> $attributes
     */
    public function add(DOMElement $parent, string $name, ?string $value = null, array $attributes = []): DOMElement
    {
        $element = $this->document->createElement($name);

        if ($value !== null && $value !== '') {
            $element->appendChild($this->document->createTextNode($value));
        }

        self::setAttributes($element, $attributes);

        $parent->appendChild($element);

        return $element;
    }

    public function document(): DOMDocument
    {
        return $this->document;
    }

    /**
     * The request as XML string, without XML declaration.
     */
    public function toXml(): string
    {
        return (string) $this->document->saveXML($this->root);
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function setAttributes(DOMElement $element, array $attributes): void
    {
        foreach ($attributes as $name => $value) {
            $element->setAttribute($name, $value);
        }
    }
}
