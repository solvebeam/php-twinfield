<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Http;

use DOMDocument;
use DOMElement;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Blocks every request that may change data in Twinfield, unless writes
 * were explicitly confirmed for that company via `composer test:write`.
 *
 * Read-only is decided strictly: anything not recognised as a read is
 * treated as a write.
 */
final readonly class WriteGuardClient implements ClientInterface
{
    /** SOAP actions that only read data (matched on the local name prefix). */
    private const READ_ACTION_PREFIXES = ['Search', 'Get', 'Query', 'Read', 'List'];

    /** ProcessXml actions; the embedded XML request decides read or write. */
    private const PROCESS_XML_ACTIONS = ['ProcessXmlString', 'ProcessXmlDocument'];

    /** Root elements of read-only ProcessXml requests: read, list and browse (`columns`). */
    private const READ_XML_ROOTS = ['read', 'list', 'columns'];

    /**
     * Root elements that read when they contain only `<office>` and `<code>`, and save otherwise.
     * Twinfield has no `<read>` type for these.
     *
     * @see https://developers.twinfield.com/documentation/api/master-data/asset-methods/#read
     */
    private const READ_BY_CODE_ROOTS = ['assetmethod'];

    /**
     * @param list<string> $writeCompanies
     */
    public function __construct(
        private ClientInterface $inner,
        private array $writeCompanies,
        private ?string $allowWrites,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $body = (string) $request->getBody();
        $action = self::actionName($request->getHeaderLine('SOAPAction'));

        if (!self::isReadOnly($action, $body)) {
            $this->assertWriteAllowed($action, $body);
        }

        return $this->inner->sendRequest($request);
    }

    public static function isReadOnly(string $action, string $body): bool
    {
        if ($action === '') {
            // Not a SOAP call (e.g. OAuth), the guard only covers SOAP.
            return true;
        }

        if (in_array($action, self::PROCESS_XML_ACTIONS, true)) {
            $requests = self::processXmlRequests($body);

            return $requests !== [] && array_filter($requests, self::isReadRequest(...)) === $requests;
        }

        foreach (self::READ_ACTION_PREFIXES as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function assertWriteAllowed(string $action, string $body): void
    {
        $companies = self::companyCodes($body);

        if ($companies === []) {
            throw new WriteBlockedException(sprintf(
                'Blocked SOAP write "%s": no company code found in the request.',
                $action,
            ));
        }

        foreach ($companies as $company) {
            if (!in_array($company, $this->writeCompanies, true)) {
                throw new WriteBlockedException(sprintf(
                    'Blocked SOAP write "%s" on company "%s": not in TWINFIELD_WRITE_COMPANIES (%s).',
                    $action,
                    $company,
                    implode(', ', $this->writeCompanies) ?: 'empty',
                ));
            }

            if ($company !== $this->allowWrites) {
                throw new WriteBlockedException(sprintf(
                    'Blocked SOAP write "%s" on company "%s": writes not confirmed. Run `composer test:write`.',
                    $action,
                    $company,
                ));
            }
        }
    }

    private static function actionName(string $soapAction): string
    {
        $soapAction = trim($soapAction, " \"'");
        $position = strrpos($soapAction, '/');

        return $position === false ? $soapAction : substr($soapAction, $position + 1);
    }

    private static function isReadRequest(DOMElement $request): bool
    {
        if (in_array($request->localName, self::READ_XML_ROOTS, true)) {
            return true;
        }

        if (!in_array($request->localName, self::READ_BY_CODE_ROOTS, true) || $request->attributes->length > 0) {
            return false;
        }

        $children = array_map(
            static fn (DOMElement $child): string => (string) $child->localName,
            self::childElements($request),
        );
        sort($children);

        return $children === ['code', 'office'];
    }

    /**
     * @return list<DOMElement>
     */
    private static function processXmlRequests(string $body): array
    {
        $envelope = self::loadXml($body);
        $payload = $envelope === null ? null : self::firstByLocalName($envelope, 'xmlRequest');

        if ($payload === null) {
            return [];
        }

        $request = self::loadXml(trim($payload->textContent));

        if ($request === null) {
            return [];
        }

        // Multiple requests may be wrapped in a <general> element.
        return $request->localName === 'general' ? self::childElements($request) : [$request];
    }

    /**
     * Company codes from the SOAP header and any `<office>` in a ProcessXml request.
     *
     * @return list<string>
     */
    private static function companyCodes(string $body): array
    {
        $envelope = self::loadXml($body);

        if ($envelope === null) {
            return [];
        }

        $codes = [];

        foreach ($envelope->getElementsByTagNameNS('*', 'CompanyCode') as $element) {
            $codes[] = trim($element->textContent);
        }

        $payload = self::firstByLocalName($envelope, 'xmlRequest');
        $request = $payload === null ? null : self::loadXml(trim($payload->textContent));

        if ($request !== null) {
            foreach ($request->getElementsByTagName('office') as $element) {
                $codes[] = trim($element->textContent);
            }
        }

        return array_values(array_unique(array_filter($codes, static fn (string $code): bool => $code !== '')));
    }

    private static function loadXml(string $xml): ?DOMElement
    {
        if ($xml === '') {
            return null;
        }

        $document = new DOMDocument();

        if (@$document->loadXML($xml) === false) {
            return null;
        }

        return $document->documentElement;
    }

    private static function firstByLocalName(DOMElement $element, string $localName): ?DOMElement
    {
        $found = $element->getElementsByTagNameNS('*', $localName)->item(0);

        return $found instanceof DOMElement ? $found : null;
    }

    /**
     * @return list<DOMElement>
     */
    private static function childElements(DOMElement $element): array
    {
        $children = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
