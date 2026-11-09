<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Supplier;

use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Dimension\DimensionRequests;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Suppliers (dimension type `CRD`) of the company.
 *
 *   $suppliers = $twinfield->company('1000')->suppliers();
 *
 *   foreach ($suppliers->search('*Example*')->inName()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $supplier = $suppliers->get('2000');
 *
 *   $created = $suppliers->create(
 *       SupplierInput::create('Example Supplies B.V.')
 *           ->dueDays(30)
 *           ->creditTransfer('SEPANLCT')
 *           ->bank(BankInput::create()->iban('NL91ABNA0417164300')->ascription('Example Supplies B.V.')->default()),
 *   );
 *
 *   $suppliers->update($supplier->edit()->dueDays(14));
 *   $suppliers->delete($created->code);
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/suppliers/
 */
final readonly class SupplierResource
{
    private DimensionRequests $requests;

    public function __construct(Twinfield $twinfield)
    {
        $this->requests = new DimensionRequests($twinfield, 'CRD', 'Supplier', 'Suppliers');
    }

    /**
     * Search suppliers, by default on code and name, e.g. `search('*Example*')->inName()->get()`.
     * Also on bank account (`inBankAccount()`) or address fields (`inAddress()`).
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<SupplierSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        return $this->requests->search($pattern, SupplierSummary::fromFinderRow(...));
    }

    /**
     * @throws DimensionNotFoundException When there is no supplier with this code.
     */
    public function get(string $code): Supplier
    {
        return Supplier::fromXml($this->requests->read($code), $this->requests->organisation());
    }

    /**
     * The supplier, or null when there is no supplier with this code.
     */
    public function find(string $code): ?Supplier
    {
        try {
            return $this->get($code);
        } catch (DimensionNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new supplier. Without a code, Twinfield gives it the next free code; with a
     * code, this reads first, so an existing supplier is never overwritten.
     *
     * @throws DimensionAlreadyExistsException When the code is already in use.
     * @throws ProcessXmlException When Twinfield refuses the supplier.
     */
    public function create(SupplierInput $supplier): Supplier
    {
        if ($supplier->code !== null && $this->find($supplier->code) !== null) {
            throw new DimensionAlreadyExistsException('CRD', $supplier->code);
        }

        return $this->save($supplier);
    }

    /**
     * Updates an existing supplier. Start from `$supplier->edit()`.
     *
     * @throws InvalidArgumentException When the input has no code.
     * @throws DimensionNotFoundException When there is no supplier with this code.
     * @throws ProcessXmlException When Twinfield refuses the changes.
     */
    public function update(SupplierInput $supplier): Supplier
    {
        if ($supplier->code === null) {
            throw new InvalidArgumentException('Updating a supplier needs its code: start from $supplier->edit().');
        }

        $this->get($supplier->code);

        return $this->save($supplier);
    }

    /**
     * Creates the supplier, or updates it when the code exists (one request, no check).
     *
     * @throws ProcessXmlException When Twinfield refuses the supplier.
     */
    public function save(SupplierInput $supplier): Supplier
    {
        $xml = $supplier->toXml($this->requests->company());

        return Supplier::fromXml($this->requests->save($xml), $this->requests->organisation());
    }

    /**
     * Deletes the supplier. When it is used in a transaction, Twinfield hides it instead.
     *
     * @throws DimensionNotFoundException When there is no supplier with this code.
     */
    public function delete(string $code): DeleteResult
    {
        return $this->requests->delete($code);
    }

    /**
     * Makes a deleted or hidden supplier active again.
     *
     * @throws DimensionNotFoundException When there is no supplier with this code.
     */
    public function activate(string $code): void
    {
        $this->requests->activate($code);
    }
}
