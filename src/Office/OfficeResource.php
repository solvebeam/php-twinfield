<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use LogicException;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Soap\ClassicServiceFaultException;
use SolveBeam\Twinfield\Soap\ErrorCode;
use SolveBeam\Twinfield\Twinfield;

/**
 * Offices (companies, administrations) the logged in user has access to. Read-only: offices
 * cannot be created or changed with the API.
 *
 *   $offices = $twinfield->offices();
 *
 *   foreach ($offices->list() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $holdings = $offices->search('*Holding*')->inName()->withoutTaxGroup()->all();
 *
 *   $office = $offices->get('1000');
 *   echo $office->general->baseCurrency->code, PHP_EOL;
 *
 *   $company = $twinfield->company($office);   // client for that office
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/offices
 */
final readonly class OfficeResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * All offices, over all Finder pages.
     *
     * @return list<OfficeSummary>
     */
    public function list(): array
    {
        return iterator_to_array($this->search()->all(), false);
    }

    /**
     * Search offices by code and name, e.g. `search('10*')->inCode()->get()`, or only the
     * offices that can join a fiscal unity: `search()->withoutTaxGroup()`.
     *
     * Works without company: the Finder searches all offices of the user.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<OfficeSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        // With includeid the columns are always the same: code, name, ID, country, VAT period, quarter.
        $query = $this->twinfield->finder(FinderType::Offices)->pattern($pattern)->option('includeid', '1');

        return CodeNameSearch::forOrganisation($query, OfficeSummary::fromFinderRow(...));
    }

    /**
     * The office with all its settings.
     *
     * Twinfield only reads an office with a company in the request. Without company, the client
     * uses the office itself, so `get()` also works on a client without company.
     *
     * @throws OfficeNotFoundException When there is no office with this code, or the user has no access.
     */
    public function get(string $code): Office
    {
        $xml = XmlBuilder::create('read');
        $xml->add($xml->root(), 'type', 'office');
        $xml->add($xml->root(), 'code', $code);

        $client = $this->twinfield->companyCode() === null ? $this->twinfield->company($code) : $this->twinfield;

        try {
            $result = $client->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            throw OfficeNotFoundException::fromProcessXml($exception, $code) ?? $exception;
        } catch (ClassicServiceFaultException $exception) {
            // With an unknown office as company, Twinfield refuses the request itself: 359, no company.
            $noCompany = ErrorCode::tryFrom($exception->errorCode) === ErrorCode::CompanyIdMissing;

            if ($noCompany && $client !== $this->twinfield) {
                throw new OfficeNotFoundException($code, $exception->errorMessage, $exception);
            }

            throw $exception;
        }

        return Office::fromXml(XmlElement::root($result), $this->twinfield->organisation());
    }

    /**
     * The office, or null when there is no office with this code.
     */
    public function find(string $code): ?Office
    {
        try {
            return $this->get($code);
        } catch (OfficeNotFoundException) {
            return null;
        }
    }

    /**
     * The office of the client: `$twinfield->company('1000')->offices()->current()`.
     *
     * @throws LogicException When the client has no company.
     */
    public function current(): Office
    {
        $code = $this->twinfield->companyCode()
            ?? throw new LogicException('The client has no company: use company(\'1000\') first, or get(\'1000\').');

        return $this->get($code);
    }
}
