<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CommandQuery;

use DateTimeInterface;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Authentication\AuthenticationHeader;
use SolveBeam\Twinfield\Soap\SoapMessage;
use SolveBeam\Twinfield\Soap\SoapRequestFactory;

/**
 * SOAP envelope for a Command & Query web service (WCF, `*.svc`):
 *
 *   <s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
 *     <s:Header>
 *       <Authentication xmlns="http://www.twinfield.com/" xmlns:h="…/Twinfield.WebServices.Shared">
 *         <h:AccessToken>…</h:AccessToken>
 *         <h:CompanyCode>1000</h:CompanyCode>
 *       </Authentication>
 *     </s:Header>
 *     <s:Body>
 *       <Query xmlns="http://www.twinfield.com/" xmlns:i="…XMLSchema-instance"
 *              xmlns:a="…/Twinfield.WebServices.PeriodService" i:type="a:GetPeriods">
 *         <a:Year>2021</a:Year>
 *       </Query>
 *     </s:Body>
 *   </s:Envelope>
 *
 * SOAPAction: `http://www.twinfield.com/PeriodService/Query` (or `/Process` for commands).
 *
 * @internal
 */
final readonly class CommandQueryEnvelope implements SoapMessage
{
    private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const TWINFIELD_NS = 'http://www.twinfield.com/';
    private const SHARED_NS = 'http://schemas.datacontract.org/2004/07/Twinfield.WebServices.Shared';
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';
    private const XMLNS_NS = 'http://www.w3.org/2000/xmlns/';

    /**
     * @param 'Query'|'Command' $element
     * @param array<string, int|string|bool|DateTimeInterface|Nil|array<string, mixed>|null> $fields
     */
    private function __construct(
        private string $service,
        private string $element,
        private string $type,
        private array $fields,
        private AuthenticationHeader $authentication,
    ) {
        if ($service === '') {
            throw new InvalidArgumentException('Service name must not be empty.');
        }
    }

    public static function query(string $service, Query $query, AuthenticationHeader $authentication): self
    {
        return new self($service, 'Query', $query->type, $query->fields, $authentication);
    }

    public static function command(string $service, Command $command, AuthenticationHeader $authentication): self
    {
        return new self($service, 'Command', $command->type, $command->fields, $authentication);
    }

    /**
     * Namespace of the service's data contracts, e.g. `…/Twinfield.WebServices.PeriodService`.
     */
    public static function serviceNamespace(string $service): string
    {
        return 'http://schemas.datacontract.org/2004/07/Twinfield.WebServices.' . $service;
    }

    /**
     * `Query` for queries, `Process` for commands.
     */
    public function soapAction(): string
    {
        return sprintf('%s%s/%s', self::TWINFIELD_NS, $this->service, $this->element === 'Query' ? 'Query' : 'Process');
    }

    public function toXml(): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $envelope = $doc->createElementNS(self::SOAP_NS, 's:Envelope');
        $doc->appendChild($envelope);

        $header = $doc->createElementNS(self::SOAP_NS, 's:Header');
        $envelope->appendChild($header);
        $header->appendChild($this->authenticationElement($doc));

        $body = $doc->createElementNS(self::SOAP_NS, 's:Body');
        $envelope->appendChild($body);
        $body->appendChild($this->payloadElement($doc));

        return (string) $doc->saveXML();
    }

    public function toRequest(
        string $url,
        SoapRequestFactory $factory,
        string $method = 'POST',
    ): RequestInterface {
        return $factory->createRequest($method, $url)
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withHeader('SOAPAction', '"' . $this->soapAction() . '"')
            ->withBody($factory->createStream($this->toXml()));
    }

    private function authenticationElement(DOMDocument $doc): DOMElement
    {
        $authentication = $doc->createElementNS(self::TWINFIELD_NS, 'Authentication');
        $authentication->setAttributeNS(self::XMLNS_NS, 'xmlns:h', self::SHARED_NS);

        $accessToken = $this->authentication->accessToken;
        $companyCode = $this->authentication->companyCode;

        $authentication->appendChild(self::textElement($doc, self::SHARED_NS, 'h:AccessToken', $accessToken));

        if ($companyCode !== null) {
            $authentication->appendChild(self::textElement($doc, self::SHARED_NS, 'h:CompanyCode', $companyCode));
        }

        return $authentication;
    }

    private function payloadElement(DOMDocument $doc): DOMElement
    {
        $namespace = self::serviceNamespace($this->service);

        $payload = $doc->createElementNS(self::TWINFIELD_NS, $this->element);
        $payload->setAttributeNS(self::XMLNS_NS, 'xmlns:i', self::XSI_NS);
        $payload->setAttributeNS(self::XMLNS_NS, 'xmlns:a', $namespace);
        $payload->setAttributeNS(self::XSI_NS, 'i:type', 'a:' . $this->type);

        $this->appendFields($doc, $payload, $namespace, $this->fields);

        return $payload;
    }

    /**
     * Appends fields in the given order. An array is a complex element with its own fields,
     * `Nil::Value` an element with `i:nil="true"`, and `null` is left out.
     *
     * @param array<array-key, mixed> $fields
     */
    private function appendFields(DOMDocument $doc, DOMElement $parent, string $namespace, array $fields): void
    {
        foreach ($fields as $name => $value) {
            if (!is_string($name) || $name === '') {
                throw new InvalidArgumentException('Command & Query field names must be non-empty strings.');
            }

            if ($value === null) {
                continue;
            }

            if ($value === Nil::Value) {
                $element = $doc->createElementNS($namespace, 'a:' . $name);
                $element->setAttributeNS(self::XSI_NS, 'i:nil', 'true');
                $parent->appendChild($element);

                continue;
            }

            if (is_array($value)) {
                $element = $doc->createElementNS($namespace, 'a:' . $name);
                $parent->appendChild($element);
                $this->appendFields($doc, $element, $namespace, $value);

                continue;
            }

            if (!is_int($value) && !is_string($value) && !is_bool($value) && !$value instanceof DateTimeInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Command & Query field "%s" has an unsupported type: %s.',
                    $name,
                    get_debug_type($value),
                ));
            }

            $parent->appendChild(self::textElement($doc, $namespace, 'a:' . $name, self::format($value)));
        }
    }

    private static function format(int|string|bool|DateTimeInterface $value): string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d\TH:i:s'),
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    private static function textElement(DOMDocument $doc, string $namespace, string $name, string $value): DOMElement
    {
        $element = $doc->createElementNS($namespace, $name);
        $element->appendChild($doc->createTextNode($value));

        return $element;
    }
}
