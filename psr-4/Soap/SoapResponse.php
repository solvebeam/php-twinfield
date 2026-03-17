<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use DOMDocument;
use DOMElement;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class SoapResponse
{
    private object|null $mappedPayload = null;

    public function __construct(
        private readonly ResponseInterface $httpResponse,
        private readonly DOMDocument $xml,
    ) {}

    public static function fromHttpResponse(ResponseInterface $response): self
    {
        $body = (string) $response->getBody();
        $xml = new DOMDocument('1.0', 'UTF-8');

        if (@$xml->loadXML($body) === false) {
            throw new RuntimeException('Unable to parse SOAP response as XML.');
        }

        return new self($response, $xml);
    }

    public function httpResponse(): ResponseInterface
    {
        return $this->httpResponse;
    }

    public function xml(): DOMDocument
    {
        return $this->xml;
    }

    public function bodyElement(): ?DOMElement
    {
        $envelope = $this->xml->documentElement;
        if (!$envelope instanceof DOMElement) {
            return null;
        }

        return $this->firstElementByLocalName($envelope, 'Body');
    }

    public function payloadElement(): ?DOMElement
    {
        $body = $this->bodyElement();
        if (!$body instanceof DOMElement) {
            return null;
        }

        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName !== 'Fault') {
                return $child;
            }
        }

        return null;
    }

    public function faultElement(): ?DOMElement
    {
        $body = $this->bodyElement();
        if (!$body instanceof DOMElement) {
            return null;
        }

        return $this->firstElementByLocalName($body, 'Fault');
    }

    public function isFault(): bool
    {
        return $this->faultElement() instanceof DOMElement;
    }

    public function faultCode(): ?string
    {
        $fault = $this->faultElement();
        if (!$fault instanceof DOMElement) {
            return null;
        }

        $code = $this->firstElementByLocalName($fault, 'faultcode');
        if ($code instanceof DOMElement) {
            return trim($code->textContent);
        }

        $soap12Code = $this->firstElementByLocalName($fault, 'Code');
        if ($soap12Code instanceof DOMElement) {
            $value = $this->firstElementByLocalName($soap12Code, 'Value');

            return $value instanceof DOMElement ? trim($value->textContent) : null;
        }

        return null;
    }

    public function faultString(): ?string
    {
        $fault = $this->faultElement();
        if (!$fault instanceof DOMElement) {
            return null;
        }

        $faultString = $this->firstElementByLocalName($fault, 'faultstring');
        if ($faultString instanceof DOMElement) {
            return trim($faultString->textContent);
        }

        $reason = $this->firstElementByLocalName($fault, 'Reason');
        if ($reason instanceof DOMElement) {
            $text = $this->firstElementByLocalName($reason, 'Text');

            return $text instanceof DOMElement ? trim($text->textContent) : null;
        }

        return null;
    }

    public function withMappedPayload(string $responseClass): self
    {
        $payloadElement = $this->payloadElement();
        if (!$payloadElement instanceof DOMElement) {
            throw new RuntimeException('No payload element found in SOAP response to map.');
        }

        $mapper = new SoapResponseMapper();
        $mappedObject = $mapper->mapFromResponseElement($responseClass, $payloadElement);

        $self = clone $this;
        $self->mappedPayload = $mappedObject;

        return $self;
    }

    public function mappedPayload(): ?object
    {
        return $this->mappedPayload;
    }

    private function firstElementByLocalName(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }
}
