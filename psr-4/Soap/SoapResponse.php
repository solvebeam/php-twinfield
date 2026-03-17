<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use DOMDocument;
use DOMElement;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class SoapResponse
{
    public function __construct(
        private readonly ResponseInterface $httpResponse,
        private readonly DOMDocument $xml,
        private readonly DOMElement $body,
    ) {}

    public static function fromHttpResponse(ResponseInterface $response): self
    {
        $body = (string) $response->getBody();
        $xml = new DOMDocument('1.0', 'UTF-8');

        if (@$xml->loadXML($body) === false) {
            var_dump( $body );
            throw new RuntimeException('Unable to parse SOAP response as XML.');
        }

        $envelope = $xml->documentElement;
        if (!$envelope instanceof DOMElement) {
            throw new RuntimeException('Unable to find SOAP envelope in response.');
        }

        $body = null;
        foreach ($envelope->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'Body') {
                $body = $child;
                break;
            }
        }

        if (!$body instanceof DOMElement) {
            throw new RuntimeException('SOAP response does not contain a soap:Body element.');
        }

        return new self($response, $xml, $body);
    }

    public function httpResponse(): ResponseInterface
    {
        return $this->httpResponse;
    }

    public function xml(): DOMDocument
    {
        return $this->xml;
    }

    public function bodyElement(): DOMElement
    {
        return $this->body;
    }

    public function payloadElement(): ?DOMElement
    {
        foreach ($this->body->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName !== 'Fault') {
                return $child;
            }
        }

        return null;
    }

    public function faultElement(): ?DOMElement
    {
        return $this->firstElementByLocalName($this->body, 'Fault');
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

    /**
     * @template T of object
     * @param class-string<T> $responseClass
     * @return T
     */
    public function mapTo(string $responseClass): object
    {
        return (new SoapResponseMapper())->mapFromResponseElement($responseClass, $this->body);
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
