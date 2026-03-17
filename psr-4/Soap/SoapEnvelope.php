<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use DOMDocument;
use DOMElement;
use LogicException;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use SolveBeam\Twinfield\Soap\SoapAction;
use SolveBeam\Twinfield\Soap\SoapName;
use SolveBeam\Twinfield\Soap\SoapHeader;

final class SoapEnvelope
{
	private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';
	private const DEFAULT_NAMESPACE = 'http://www.twinfield.com/';

	public function __construct(
		private readonly object $body,
		private readonly object|null $header = null,
		private readonly string $namespace = self::DEFAULT_NAMESPACE,
	) {}

	public function toXml(): string
	{
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;

		$bodyAttr = $this->readRequiredClassAttribute($this->body, SoapName::class);
		$bodyElement = $bodyAttr->name;

		$envelope = $doc->createElementNS(self::SOAP_NS, 'soap:Envelope');
		$doc->appendChild($envelope);
		if ($this->namespace !== '') {
			$envelope->setAttribute('xmlns', $this->namespace);
		}

		if ($this->header !== null) {
			$attr = $this->readRequiredClassAttribute($this->header, SoapHeader::class);
			$namespace = $attr?->namespace ?: null;
			$element = $attr->element;

			$soapHeader = $doc->createElementNS(self::SOAP_NS, 'soap:Header');

			$soapHeader->appendChild($this->buildInnerElement($doc, $this->header, $namespace, $element));
			$envelope->appendChild($soapHeader);
		}

		$soapBody = $doc->createElementNS(self::SOAP_NS, 'soap:Body');
		$soapBody->appendChild($this->buildInnerElement($doc, $this->body, $this->namespace, $bodyElement));
		$envelope->appendChild($soapBody);

		return (string) $doc->saveXML();
	}

	public function toRequest(
		string $url,
		SoapRequestFactory $factory,
		string $method = 'POST',
	): RequestInterface {
		$actionAttr = $this->readRequiredClassAttribute($this->body, SoapAction::class);
		$action = $actionAttr->action;

		return $factory->createRequest($method, $url)
			->withHeader('Content-Type', 'text/xml; charset=utf-8')
			->withHeader('SOAPAction', $action)
			->withBody($factory->createStream($this->toXml()));
	}

	private function buildInnerElement(
		DOMDocument $doc,
		object $element,
		?string $namespace,
		string $elementName,
	): DOMElement {
		$inner = $doc->createElementNS($namespace, $elementName);

		foreach ((new ReflectionClass($element))->getProperties() as $prop) {
			$nameAttrs = $prop->getAttributes(SoapName::class);
			$xmlName = $nameAttrs !== []
				? $nameAttrs[0]->newInstance()->name
				: $prop->getName();

			$child = $doc->createElementNS($namespace, $xmlName, (string) $prop->getValue($element));
			$inner->appendChild($child);
		}

		return $inner;
	}

	private function readAttribute(object $object, string $attributeClass): ?object
	{
		$attrs = (new ReflectionClass($object))->getAttributes($attributeClass);

		return $attrs !== [] ? $attrs[0]->newInstance() : null;
	}

	private function readRequiredClassAttribute(object $object, string $attributeClass): object
	{
		$attribute = $this->readAttribute($object, $attributeClass);

		if ($attribute === null) {
			throw new LogicException(sprintf(
				'Class "%s" must be annotated with #[%s].',
				$object::class,
				basename(str_replace('\\\\', '/', $attributeClass))
			));
		}

		return $attribute;
	}
}
