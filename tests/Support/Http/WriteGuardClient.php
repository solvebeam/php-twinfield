<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Http;

use Closure;
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
 *
 * REST API requests (`…/Api/…`) are reads for GET, HEAD and OPTIONS. Writes name the company by GUID
 * in the path; the guard turns it into a company code with `$companyCodeOf`, and blocks the write when
 * that is not possible.
 */
final readonly class WriteGuardClient implements ClientInterface
{
    /** SOAP actions that only read data (matched on the local name prefix). */
    private const READ_ACTION_PREFIXES = ['Search', 'Get', 'Query', 'Read', 'List'];

    /**
     * SOAP actions that only read data, matched exactly: `Load` of hierarchies.asmx (a prefix would
     * also match a future `LoadAndSave`).
     *
     * @see https://developers.twinfield.com/documentation/api/miscellaneous/hierarchy/#load
     */
    private const READ_ACTIONS = ['Load'];

    /** ProcessXml actions; the embedded XML request decides read or write. */
    private const PROCESS_XML_ACTIONS = ['ProcessXmlString', 'ProcessXmlDocument'];

    /** Root elements of read-only ProcessXml requests: read, list and browse (`columns`). */
    private const READ_XML_ROOTS = ['read', 'list', 'columns'];

    /**
     * Root elements that read when they contain only `<office>` and `<code>`, and save otherwise.
     * Twinfield has no `<read>` type for these.
     *
     * @see https://developers.twinfield.com/documentation/api/master-data/asset-methods/#read
     * @see https://developers.twinfield.com/documentation/api/master-data/dimension-types/#read
     */
    private const READ_BY_CODE_ROOTS = ['assetmethod', 'dimensiontype'];

    /**
     * Root elements that read only with this `action`; every other action (or none) changes data.
     *
     * @see https://developers.twinfield.com/documentation/api/transaction-data/reversal-transactions/#get
     */
    private const READ_ACTION_ROOTS = ['reverse' => 'getreversetransactioninfo'];

    /** HTTP methods of REST API requests that only read. */
    private const REST_READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * REST API POST requests that only read: queueing a fixed asset report (scope `readFixedAssets`).
     */
    private const REST_READ_POSTS = ['~/fixedassets/backgroundreports/reconciliation$~'];

    /**
     * @param list<string>                     $writeCompanies
     * @param ?Closure(string): ?string $companyCodeOf Company code of a company GUID, for REST writes.
     */
    public function __construct(
        private ClientInterface $inner,
        private array $writeCompanies,
        private ?string $allowWrites,
        private ?Closure $companyCodeOf = null,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (self::isRest($path)) {
            if (!self::isRestReadOnly($request->getMethod(), $path)) {
                $this->assertRestWriteAllowed($request->getMethod(), $path);
            }

            return $this->inner->sendRequest($request);
        }

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

        if (in_array($action, self::READ_ACTIONS, true)) {
            return true;
        }

        foreach (self::READ_ACTION_PREFIXES as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function isRest(string $path): bool
    {
        return str_starts_with($path, '/Api/');
    }

    public static function isRestReadOnly(string $method, string $path): bool
    {
        if (in_array(strtoupper($method), self::REST_READ_METHODS, true)) {
            return true;
        }

        if (strtoupper($method) !== 'POST') {
            return false;
        }

        foreach (self::REST_READ_POSTS as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    private function assertRestWriteAllowed(string $method, string $path): void
    {
        $action = $method . ' ' . $path;

        if (preg_match('~/companies/([0-9a-fA-F-]{36})(/|$)~', $path, $matches) !== 1) {
            throw new WriteBlockedException(sprintf(
                'Blocked REST write "%s": no company GUID found in the path.',
                $action,
            ));
        }

        $company = $this->companyCodeOf === null ? null : ($this->companyCodeOf)($matches[1]);

        if ($company === null) {
            throw new WriteBlockedException(sprintf(
                'Blocked REST write "%s": company %s is not an office of the user.',
                $action,
                $matches[1],
            ));
        }

        $this->assertCompanyAllowed('REST', $action, $company);
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
            $this->assertCompanyAllowed('SOAP', $action, $company);
        }
    }

    private function assertCompanyAllowed(string $kind, string $action, string $company): void
    {
        if (!in_array($company, $this->writeCompanies, true)) {
            throw new WriteBlockedException(sprintf(
                'Blocked %s write "%s" on company "%s": not in TWINFIELD_WRITE_COMPANIES (%s).',
                $kind,
                $action,
                $company,
                implode(', ', $this->writeCompanies) ?: 'empty',
            ));
        }

        if ($company !== $this->allowWrites) {
            throw new WriteBlockedException(sprintf(
                'Blocked %s write "%s" on company "%s": writes not confirmed. Run `composer test:write`.',
                $kind,
                $action,
                $company,
            ));
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

        $readAction = self::READ_ACTION_ROOTS[(string) $request->localName] ?? null;

        if ($readAction !== null) {
            return $request->attributes->length === 1 && $request->getAttribute('action') === $readAction;
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
