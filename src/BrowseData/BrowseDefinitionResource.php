<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

use DOMDocument;
use DOMElement;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlString;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Browse definitions and browse fields: what you need to design a browse query.
 *
 *   $definitions = $twinfield->company('1000')->browseDefinitions();
 *
 *   $definition = $definitions->get(BrowseCode::GeneralLedgerDetailsV3);
 *   $fields = $definitions->fields();
 *
 * @see https://developers.twinfield.com/documentation/api/request-data/browse-data/
 */
final readonly class BrowseDefinitionResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * The definition of a browse code in the company of the client.
     *
     * @throws \SolveBeam\Twinfield\ProcessXml\ProcessXmlException For an unknown browse code
     *                                                           (`Opvraagcode 999 bestaat niet.`).
     */
    public function get(BrowseCode|string $code): BrowseDefinition
    {
        $code = $code instanceof BrowseCode ? $code->value : $code;
        $company = $this->twinfield->companyReference();

        $xml = XmlBuilder::create('read');
        $xml->add($xml->root(), 'type', 'browse');
        $xml->add($xml->root(), 'office', $company->code);
        $xml->add($xml->root(), 'code', $code);

        $browse = XmlElement::root($this->twinfield->processXml($xml->toXml()));
        $definition = BrowseDefinition::fromXml($browse, $company);

        if ($definition->code !== $code) {
            throw new UnexpectedResponseException(sprintf(
                'Asked for browse definition %s, got %s.',
                $code,
                $definition->code,
            ));
        }

        return $definition;
    }

    /**
     * All browse fields with their data type and, for fields with a fixed list of values, the
     * values. Which fields a browse code has is in its definition.
     *
     * @return list<BrowseField>
     */
    public function fields(): array
    {
        $xml = XmlBuilder::create('list');
        $xml->add($xml->root(), 'type', 'browsefields');

        $response = ProcessXmlResult::xmlOf(
            $this->twinfield->call('processxml', ProcessXmlString::fromXml($xml->toXml())),
        );

        return array_map(BrowseField::fromXml(...), self::browseFields($response)->list('browsefield'));
    }

    /**
     * Unlike other ProcessXml responses, `<browsefields>` has no `result` attribute. An error
     * response has one, so read that as a regular result.
     */
    private static function browseFields(string $response): XmlElement
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = trim($response) !== '' && $document->loadXML($response, LIBXML_NONET | LIBXML_PARSEHUGE);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;

        if (!$loaded || !$root instanceof DOMElement) {
            throw new UnexpectedResponseException('ProcessXmlStringResult does not contain valid XML.');
        }

        if ($root->hasAttribute('result')) {
            ProcessXmlResult::fromXml($response)->throwIfFailed();
        }

        if ($root->localName !== 'browsefields') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a <browsefields> response, got <%s>.',
                $root->localName,
            ));
        }

        return new XmlElement($root, 'browsefields');
    }
}
