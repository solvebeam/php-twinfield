<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CostCenter;

use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Dimension\DimensionRequests;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Twinfield;

/**
 * Cost centers (dimension type `KPL`) of the company: the second dimension (`dim2`) of
 * transaction lines on profit and loss accounts.
 *
 *   $costCenters = $twinfield->company('1000')->costCenters();
 *
 *   foreach ($costCenters->search()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $costCenter = $costCenters->get('100');
 *
 *   $costCenters->create(CostCenterInput::create('200', 'Marketing'));
 *   $costCenters->update($costCenter->edit()->name('Sales & marketing'));
 *   $costCenters->delete('200');
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/cost-centers/
 */
final readonly class CostCenterResource
{
    private DimensionRequests $requests;

    public function __construct(Twinfield $twinfield)
    {
        $this->requests = new DimensionRequests($twinfield, 'KPL', 'Cost center', 'Cost centers');
    }

    /**
     * Search cost centers by code and name, e.g. `search('1*')->inCode()->get()`.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<CostCenterSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        return $this->requests->search($pattern, CostCenterSummary::fromFinderRow(...));
    }

    /**
     * @throws DimensionNotFoundException When there is no cost center with this code.
     */
    public function get(string $code): CostCenter
    {
        return CostCenter::fromXml($this->requests->read($code), $this->requests->organisation());
    }

    /**
     * The cost center, or null when there is no cost center with this code.
     */
    public function find(string $code): ?CostCenter
    {
        try {
            return $this->get($code);
        } catch (DimensionNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new cost center. Reads first, so an existing cost center is never overwritten.
     *
     * @throws DimensionAlreadyExistsException When the code is already in use.
     */
    public function create(CostCenterInput $costCenter): CostCenter
    {
        if ($this->find($costCenter->code) !== null) {
            throw new DimensionAlreadyExistsException('KPL', $costCenter->code);
        }

        return $this->save($costCenter);
    }

    /**
     * Updates an existing cost center. Start from `$costCenter->edit()`.
     *
     * @throws DimensionNotFoundException When there is no cost center with this code.
     */
    public function update(CostCenterInput $costCenter): CostCenter
    {
        $this->get($costCenter->code);

        return $this->save($costCenter);
    }

    /**
     * Creates the cost center, or updates it when the code exists (one request, no check).
     */
    public function save(CostCenterInput $costCenter): CostCenter
    {
        $xml = $costCenter->toXml($this->requests->company());

        return CostCenter::fromXml($this->requests->save($xml), $this->requests->organisation());
    }

    /**
     * Deletes the cost center. When it is used in a transaction, Twinfield hides it instead.
     *
     * @throws DimensionNotFoundException When there is no cost center with this code.
     */
    public function delete(string $code): DeleteResult
    {
        return $this->requests->delete($code);
    }

    /**
     * Makes a deleted or hidden cost center active again.
     *
     * @throws DimensionNotFoundException When there is no cost center with this code.
     */
    public function activate(string $code): void
    {
        $this->requests->activate($code);
    }
}
