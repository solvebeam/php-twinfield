<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ProcessXml;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;
use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * ProcessXmlString call of the XML web services (`processxml.asmx`).
 *
 * @see https://developers.twinfield.com/documentation/api/types/xml-web-services/
 */
#[SoapName('ProcessXmlString')]
#[SoapAction(action: 'http://www.twinfield.com/ProcessXmlString')]
final readonly class ProcessXmlString
{
    public function __construct(
        #[SoapName('xmlRequest')]
        public string $xmlRequest,
    ) {
    }

    /**
     * Twinfield asks for XML without comments or whitespace between elements, in UTF-8.
     * The given document is copied, not changed.
     */
    public static function fromXml(DOMDocument|string $request): self
    {
        $root = ($request instanceof DOMDocument ? $request : self::parse($request))->documentElement;

        if (!$root instanceof DOMElement) {
            throw new InvalidArgumentException('The ProcessXml request has no root element.');
        }

        if ($root->localName === 'general') {
            throw new InvalidArgumentException(
                'The <general> root element is deprecated by Twinfield: send one request per type instead, '
                . 'e.g. <transactions> or <dimensions>.',
            );
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($root, true));

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(
            '//comment() | //text()[normalize-space(.) = "" and (preceding-sibling::* or following-sibling::*)]',
        );

        foreach ($nodes === false ? [] : iterator_to_array($nodes) as $node) {
            if ($node instanceof DOMNode) {
                $node->parentNode?->removeChild($node);
            }
        }

        return new self((string) $document->saveXML($document->documentElement));
    }

    private static function parse(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (trim($xml) === '' || !$document->loadXML($xml, LIBXML_NONET)) {
                $error = libxml_get_last_error();

                throw new InvalidArgumentException(sprintf(
                    'The ProcessXml request is not valid XML%s',
                    $error === false ? '.' : ': ' . trim($error->message) . '.',
                ));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }
}
