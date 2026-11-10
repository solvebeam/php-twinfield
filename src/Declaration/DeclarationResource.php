<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration;

use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMText;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Soap\ClassicServiceFaultException;
use SolveBeam\Twinfield\Soap\SoapResponse;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * VAT, ICT and tax group returns (declarations.asmx): list them, download them as XBRL or XML,
 * get the payment reference, and set their status.
 *
 *   $declarations = $twinfield->company('1000')->declarations();
 *
 *   foreach ($declarations->list(year: 2026) as $declaration) {
 *       if ($declaration->type === DeclarationType::VatTurnover) {
 *           file_put_contents($declaration->id . '.xbrl', $declarations->vatReturnXbrl($declaration->id));
 *       }
 *   }
 *
 * Every request needs a company in the header, also the ones for the whole organisation
 * (`allForOrganisation()`, `listForOrganisation()`). Twinfield refusals are thrown as
 * DeclarationException; an unknown document ID as ClassicServiceFaultException.
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/declarations/
 */
final readonly class DeclarationResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * All returns of the company, by ID (oldest first).
     *
     * An unknown company code gives an empty list, not an error.
     *
     * @return list<DeclarationSummary>
     */
    public function all(): array
    {
        return $this->summaries(new Request\GetAllSummaries($this->companyCode()));
    }

    /**
     * All returns of every company of the organisation the user has access to (`*`), by ID.
     *
     * @return list<DeclarationSummary>
     */
    public function allForOrganisation(): array
    {
        $this->companyCode();

        return $this->summaries(new Request\GetAllSummaries('*'));
    }

    /**
     * Number of returns of the company. There is no count for the whole organisation.
     */
    public function count(): int
    {
        $payload = $this->send(new Request\GetNumberOfDeclarations($this->companyCode()));

        return $payload->int('numberOfVatReturns');
    }

    /**
     * A page of the company's returns, by ID (oldest first). Optionally only the returns of a
     * year, or the ones created or changed at or after `$modifiedSince` (to the second, in the
     * time zone of the date), or both.
     *
     *   $page = $declarations->list(offset: 0, limit: 100, year: 2026);
     *   $changed = $declarations->list(modifiedSince: new DateTimeImmutable('-1 day'));
     *
     * @param int $offset Number of returns to skip; Twinfield's `startIndex` is 0-based.
     *
     * @return list<DeclarationSummary>
     */
    public function list(
        int $offset = 0,
        int $limit = 100,
        ?int $year = null,
        ?DateTimeInterface $modifiedSince = null,
    ): array {
        return $this->summaries($this->range($this->companyCode(), $offset, $limit, $year, $modifiedSince));
    }

    /**
     * Like list(), for every company of the organisation the user has access to (`*`).
     *
     * @return list<DeclarationSummary>
     */
    public function listForOrganisation(
        int $offset = 0,
        int $limit = 100,
        ?int $year = null,
        ?DateTimeInterface $modifiedSince = null,
    ): array {
        $this->companyCode();

        return $this->summaries($this->range('*', $offset, $limit, $year, $modifiedSince));
    }

    /**
     * A VAT return (VATTURNOVER, YEARLYSUPPLETION) in XBRL, as sent to the tax authorities.
     *
     * @param bool $messageId Twinfield's `isMessageIdRequired`: fill in the
     *                        MessageReferenceSupplierVAT element. Live, it is filled in either way.
     *
     * @return string An XML document with `xbrli:xbrl` as the root.
     */
    public function vatReturnXbrl(int $id, bool $messageId = false): string
    {
        return $this->document(new Request\GetVatReturnAsXbrl(self::id($id), $messageId));
    }

    /**
     * A VAT return in Twinfield's older XML format (`AangifteOmzetbelasting`).
     */
    public function vatReturnXml(int $id): string
    {
        return $this->document(new Request\GetVatReturnAsXml(self::id($id)));
    }

    /**
     * Payment reference (betalingskenmerk) of a VAT return, e.g. `7222629111601270+`.
     *
     * @return string|null Null when Twinfield has none: live, for every yearly supplementary
     *                     return (YEARLYSUPPLETION) and for the oldest VAT return (2010).
     *
     * @throws DeclarationException With InvalidDocumentType for an ICT return.
     */
    public function vatReturnPaymentReference(int $id): ?string
    {
        return $this->paymentReference(new Request\GetVatReturnPaymentReference(self::id($id)));
    }

    /**
     * An ICT return (VATICT) in XBRL.
     *
     * @param bool $messageId Twinfield's `isMessageIdRequired`: fill in the MessageReferenceSupplierICT element.
     */
    public function ictReturnXbrl(int $id, bool $messageId = false): string
    {
        return $this->document(new Request\GetIctReturnAsXbrl(self::id($id), $messageId));
    }

    /**
     * An ICT return in Twinfield's older XML format (`OpgaafICP`).
     */
    public function ictReturnXml(int $id): string
    {
        return $this->document(new Request\GetIctReturnAsXml(self::id($id)));
    }

    /**
     * A tax group return (TAXGROUP, TXGRPYRSP) in XBRL.
     *
     * @param bool $messageId Twinfield's `isMessageIdRequired`: fill in the MessageReferenceSupplierVAT element.
     */
    public function taxGroupVatReturnXbrl(int $id, bool $messageId = false): string
    {
        return $this->document(new Request\GetTaxGroupVatReturnAsXbrl(self::id($id), $messageId));
    }

    /**
     * A tax group return in Twinfield's older XML format (`AangifteOmzetbelasting`).
     */
    public function taxGroupVatReturnXml(int $id): string
    {
        return $this->document(new Request\GetTaxGroupVatReturnAsXml(self::id($id)));
    }

    /**
     * Payment reference (betalingskenmerk) of a tax group return (TAXGROUP).
     *
     * @return string|null Null when Twinfield has none (an empty element), see vatReturnPaymentReference().
     *
     * @throws DeclarationException With InvalidDocumentType for a yearly supplementary tax group
     *                              return (TXGRPYRSP).
     */
    public function taxGroupVatReturnPaymentReference(int $id): ?string
    {
        return $this->paymentReference(new Request\GetTaxGroupVatReturnPaymentReference(self::id($id)));
    }

    /**
     * Marks the return as sent to the tax authorities.
     *
     * @throws DeclarationException With a DeclarationStatusMessage code, e.g. when the status
     *                              can't change from the current one.
     */
    public function setToSent(int $id): void
    {
        $this->command(new Request\SetToSent(self::id($id)));
    }

    /**
     * Marks the return as approved by the tax authorities.
     *
     * @throws DeclarationException With a DeclarationStatusMessage code.
     */
    public function setToApproved(int $id): void
    {
        $this->command(new Request\SetToApproved(self::id($id)));
    }

    /**
     * Marks the return as rejected by the tax authorities.
     *
     * @param string $reason Why it was rejected. The WSDL allows leaving it out, the
     *                       documentation calls it mandatory; the SDK requires it.
     *
     * @throws DeclarationException With a DeclarationStatusMessage code.
     */
    public function setToRejected(int $id, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Give the reason why the return is rejected.');
        }

        $this->command(new Request\SetToRejected(self::id($id), $reason));
    }

    /**
     * One of the four GetRangeOfSummaries operations, by the filters given.
     */
    private function range(
        string $companyCode,
        int $offset,
        int $limit,
        ?int $year,
        ?DateTimeInterface $modifiedSince,
    ): object {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be 0 or more.');
        }

        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be at least 1.');
        }

        if ($year !== null && $year < 1) {
            throw new InvalidArgumentException(sprintf('Year %d is not a valid year.', $year));
        }

        if ($year === null) {
            return $modifiedSince === null
                ? new Request\GetRangeOfSummaries($companyCode, $offset, $limit)
                : new Request\GetRangeOfSummariesSinceModified($companyCode, $offset, $limit, $modifiedSince);
        }

        return $modifiedSince === null
            ? new Request\GetRangeOfSummariesByDeclarationYear($companyCode, $year, $offset, $limit)
            : new Request\GetRangeOfSummariesByDeclarationYearAndSinceModified(
                $companyCode,
                $year,
                $offset,
                $limit,
                $modifiedSince,
            );
    }

    /**
     * @return list<DeclarationSummary>
     */
    private function summaries(object $request): array
    {
        $payload = $this->send($request);
        $organisation = $this->twinfield->organisation();

        return array_map(
            static function (XmlElement $item) use ($organisation): DeclarationSummary {
                $timeFrame = $item->child('DocumentTimeFrame');
                $status = $item->child('Status');
                $assignee = $item->child('Assignee');
                $company = $item->child('Company');

                return new DeclarationSummary(
                    id: $item->int('Id'),
                    type: $item->enum('DocumentCode', DeclarationType::class),
                    name: $item->string('Name'),
                    year: $timeFrame->int('Year'),
                    period: $timeFrame->string('Period'),
                    status: new DeclarationStatus(
                        step: self::step($status),
                        description: $status->string('Description'),
                        // Empty in recent returns, left out in the oldest ones (2010, 2011).
                        extraInformation: self::nullIfEmpty($status->nullableChild('ExtraInformation')?->text()),
                    ),
                    assignee: new NamedReference($assignee->string('Code'), $assignee->string('Name')),
                    company: new CompanyReference($organisation, $company->string('Code')),
                    companyName: $company->string('Name'),
                );
            },
            $payload->child('vatReturn')->list('DeclarationSummary'),
        );
    }

    private static function step(XmlElement $status): DeclarationStatusStep
    {
        return DeclarationStatusStep::tryFrom($status->int('StepIndex'))
            ?? throw $status->child('StepIndex')->invalid('a status step from 1 to 8');
    }

    /**
     * The XML document in `vatReturn`, as a standalone document.
     */
    private function document(object $request): string
    {
        $vatReturn = $this->send($request)->child('vatReturn');
        $root = null;

        foreach ($vatReturn->element->childNodes as $node) {
            if ($node instanceof DOMText && trim($node->data) === '') {
                continue;
            }

            if (!$node instanceof DOMElement || $root !== null) {
                throw new UnexpectedResponseException(sprintf(
                    '%s must contain one XML document, got other content.',
                    $vatReturn->path,
                ));
            }

            $root = $node;
        }

        if ($root === null) {
            throw new UnexpectedResponseException(sprintf('%s is empty.', $vatReturn->path));
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($root, true));

        return (string) $document->saveXML();
    }

    private function paymentReference(object $request): ?string
    {
        // The element is always there; it is empty when the return has no payment reference.
        return $this->send($request)->nullableString('paymentReference');
    }

    private function command(object $request): void
    {
        $this->send($request);
    }

    /**
     * Sends the request and returns the `<Operation>Response` element, after throwing the error
     * messages in `<Operation>Result`. The operation is the short class name of the request.
     *
     * @throws DeclarationException
     * @throws ClassicServiceFaultException
     */
    private function send(object $request): XmlElement
    {
        $operation = substr($request::class, (int) strrpos($request::class, '\\') + 1);
        $response = $this->twinfield->call('declarations', $request);
        $payload = self::payload($response, $operation);
        $errors = array_values(array_filter(
            self::messages($payload->child($operation . 'Result'), $operation),
            static fn (DeclarationMessage $message): bool => $message->type === DeclarationMessageType::Error,
        ));

        if ($errors !== []) {
            throw new DeclarationException($operation, $errors);
        }

        return $payload;
    }

    private static function payload(SoapResponse $response, string $operation): XmlElement
    {
        $element = $response->payloadElement();
        $expected = $operation . 'Response';

        if ($element === null || $element->localName !== $expected) {
            throw new UnexpectedResponseException(sprintf(
                'Expected <%s>, got %s.',
                $expected,
                $element === null ? 'an empty SOAP body' : '<' . $element->localName . '>',
            ));
        }

        return new XmlElement($element, $expected);
    }

    /**
     * Messages of a query (`MessageOfLoadMessage`) or a status command (`MessageOfSetStatusMessages`).
     *
     * @return list<DeclarationMessage>
     */
    private static function messages(XmlElement $result, string $operation): array
    {
        [$itemName, $codes] = str_starts_with($operation, 'SetTo')
            ? ['MessageOfSetStatusMessages', DeclarationStatusMessage::class]
            : ['MessageOfLoadMessage', DeclarationLoadMessage::class];

        return array_map(
            static fn (XmlElement $message): DeclarationMessage => new DeclarationMessage(
                type: $message->enum('Type', DeclarationMessageType::class),
                code: $message->enum('Code', $codes),
                text: $message->string('Text'),
                parameters: array_map(self::parameter(...), $message->child('Parameters')->list('string')),
            ),
            $result->list($itemName),
        );
    }

    /**
     * Null for `<string xsi:nil="true"/>`, e.g. the company code when none was sent.
     */
    private static function parameter(XmlElement $parameter): ?string
    {
        $nil = $parameter->element->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'nil');

        return $nil === 'true' ? null : $parameter->text();
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function companyCode(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Declarations need a company in the request header: use company() first.');
    }

    private static function id(int $id): int
    {
        if ($id < 1) {
            throw new InvalidArgumentException(sprintf('Document ID %d is not valid.', $id));
        }

        return $id;
    }
}
