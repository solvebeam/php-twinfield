<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use LogicException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * VAT codes: the VAT rates of a company over time, and the accounts and VAT return boxes the
 * VAT goes to. Transaction lines, customers, suppliers, articles and general ledger accounts
 * refer to a VAT code by its code.
 *
 *   $vatCodes = $twinfield->company('1000')->vatCodes();
 *
 *   foreach ($vatCodes->search()->vatType(VatCodeType::Sales)->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $vatCode = $vatCodes->get('VH');
 *   echo $vatCode->vatOn('100.00', new DateTimeImmutable('today')), PHP_EOL;   // 21.00
 *
 *   $vatCodes->create(
 *       VatCodeInput::sales('VH', 'BTW 21%')->percentage(
 *           VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21')
 *               ->account(VatAccountInput::vat('1530')->group('NL', 'NL1A')),
 *       ),
 *   );
 *   $vatCodes->update($vatCode->edit()->newPercentageFrom(new DateTimeImmutable('2027-01-01'), '22'));
 *   $vatCodes->delete('VH');
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/vat/
 */
final readonly class VatCodeResource
{
    private const REST_PATH = '/organisations/{organisationId}/companies/{companyId}/vat/vatcodes';

    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search VAT codes by code and name, e.g. `search('V*')->vatType(VatCodeType::Sales)->get()`.
     *
     * Finder type `VAT`; the company comes from the client, so no `office` option is sent.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<VatCodeSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        $query = $this->twinfield->finder(FinderType::VatCodes);

        return CodeNameSearch::forCompany($query, VatCodeSummary::fromFinderRow(...))->pattern($pattern);
    }

    /**
     * The VAT code with all its percentages and accounts.
     *
     * @throws VatCodeNotFoundException When there is no VAT code with this code.
     */
    public function get(string $code): VatCode
    {
        $company = $this->company();

        $xml = XmlBuilder::create('read');
        $xml->add($xml->root(), 'office', $company);
        $xml->add($xml->root(), 'type', 'vat');
        $xml->add($xml->root(), 'code', $code);

        return VatCode::fromXml(
            XmlElement::root($this->send($xml, $code)),
            new CompanyReference($this->twinfield->organisation(), $company),
        );
    }

    /**
     * The VAT code, or null when there is no VAT code with this code.
     */
    public function find(string $code): ?VatCode
    {
        try {
            return $this->get($code);
        } catch (VatCodeNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new VAT code. Reads first, so an existing VAT code is never overwritten.
     *
     * @throws VatCodeAlreadyExistsException When the code is already in use.
     */
    public function create(VatCodeInput $vatCode): VatCode
    {
        if ($this->find($vatCode->code) !== null) {
            throw new VatCodeAlreadyExistsException($vatCode->code);
        }

        return $this->save($vatCode);
    }

    /**
     * Updates an existing VAT code with all values of the input. Start from `$vatCode->edit()`
     * to keep the values and percentages you do not change.
     *
     * @throws VatCodeNotFoundException When there is no VAT code with this code.
     */
    public function update(VatCodeInput $vatCode): VatCode
    {
        $this->get($vatCode->code);

        return $this->save($vatCode);
    }

    /**
     * Creates the VAT code, or updates it when the code exists (no existence check). Reads it
     * back afterwards, so you always get the complete VAT code.
     *
     * @throws ProcessXmlException When Twinfield refuses the VAT code.
     */
    public function save(VatCodeInput $vatCode): VatCode
    {
        // Saving creates a missing VAT code, so errors are not mapped to VatCodeNotFoundException.
        $this->twinfield->processXml($vatCode->toXml($this->company())->toXml());

        return $this->get($vatCode->code);
    }

    /**
     * Deletes the VAT code. When it is in use, Twinfield hides it instead.
     *
     * @throws VatCodeNotFoundException When there is no VAT code with this code.
     */
    public function delete(string $code): DeleteResult
    {
        $status = $this->changeStatus($code, VatCodeStatus::Deleted);

        return match ($status) {
            VatCodeStatus::Deleted => DeleteResult::Deleted,
            VatCodeStatus::Hidden => DeleteResult::Hidden,
            VatCodeStatus::Active => throw new UnexpectedResponseException(sprintf(
                'VAT code %s is still active after deleting it.',
                $code,
            )),
        };
    }

    /**
     * Makes a deleted or hidden VAT code active again.
     *
     * @throws VatCodeNotFoundException When there is no VAT code with this code.
     */
    public function activate(string $code): void
    {
        $this->changeStatus($code, VatCodeStatus::Active);
    }

    private function changeStatus(string $code, VatCodeStatus $status): VatCodeStatus
    {
        $xml = XmlBuilder::create('vat', ['status' => $status->value]);
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $code);

        return XmlElement::root($this->send($xml, $code))->enumAttribute('status', VatCodeStatus::class);
    }

    private function send(XmlBuilder $xml, string $code): ProcessXmlResult
    {
        try {
            return $this->twinfield->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            throw VatCodeNotFoundException::fromProcessXml($exception, $code) ?? $exception;
        }
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('VAT codes belong to a company: use company() first.');
    }

    /**
     * Search the VAT codes on the REST API, with their GUIDs, rates over time and allocation rules
     * (accounts and VAT groups by GUID). Scope `readVat`.
     *
     *   $search = $twinfield->company('1000')->vatCodes()->searchDetails()->type(VatCodeType::Sales);
     *
     *   foreach ($search->all() as $vatCode) {
     *       echo $vatCode->code, ' ', $vatCode->rateOn(new DateTimeImmutable())?->percentage, PHP_EOL;
     *   }
     *
     * @see https://api.accounting.twinfield.com/Api/swagger/ui/index#!/VAT32Codes
     */
    public function searchDetails(string $pattern = '*'): VatCodeDetailsSearch
    {
        $search = new VatCodeDetailsSearch($this->twinfield, self::REST_PATH);

        return $pattern === '*' ? $search : $search->pattern($pattern);
    }

    /**
     * A VAT code by GUID on the REST API, e.g. the `vatId` of a sales article. Scope `readVat`.
     *
     * @throws NotFoundException When the company has no VAT code with this GUID.
     */
    public function getDetails(string $id): VatCodeDetails
    {
        $json = $this->twinfield->rest('GET', self::REST_PATH . '/' . Guid::of($id))->json();

        return VatCodeDetailsReader::read($json, $this->twinfield->companyReference());
    }
}
