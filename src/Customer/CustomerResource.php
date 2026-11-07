<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Dimension\DimensionRequests;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Customers (dimension type `DEB`) of the company.
 *
 *   $customers = $twinfield->company('1000')->customers();
 *
 *   foreach ($customers->search('*Example*')->inName()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $customer = $customers->get('1000');
 *
 *   $created = $customers->create(
 *       CustomerInput::create('Example B.V.')
 *           ->dueDays(30)
 *           ->address(AddressInput::invoice('Example B.V.')->city('Amsterdam')->country('NL')->default()),
 *   );
 *
 *   $customers->update($customer->edit()->dueDays(14));
 *   $customers->delete($created->code);
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/customers/
 */
final readonly class CustomerResource
{
    private DimensionRequests $requests;

    public function __construct(Twinfield $twinfield)
    {
        $this->requests = new DimensionRequests($twinfield, 'DEB', 'Customer', 'Customers');
    }

    /**
     * Search customers, by default on code and name, e.g. `search('*Example*')->inName()->get()`.
     * Also on bank account (`inBankAccount()`) or address fields (`inAddress()`).
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<CustomerSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        return $this->requests->search($pattern, CustomerSummary::fromFinderRow(...));
    }

    /**
     * @throws DimensionNotFoundException When there is no customer with this code.
     */
    public function get(string $code): Customer
    {
        return Customer::fromXml($this->requests->read($code));
    }

    /**
     * The customer, or null when there is no customer with this code.
     */
    public function find(string $code): ?Customer
    {
        try {
            return $this->get($code);
        } catch (DimensionNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new customer. Without a code, Twinfield gives it the next free code; with a
     * code, this reads first, so an existing customer is never overwritten.
     *
     * @throws DimensionAlreadyExistsException When the code is already in use.
     * @throws ProcessXmlException When Twinfield refuses the customer.
     */
    public function create(CustomerInput $customer): Customer
    {
        if ($customer->code !== null && $this->find($customer->code) !== null) {
            throw new DimensionAlreadyExistsException('DEB', $customer->code);
        }

        return $this->save($customer);
    }

    /**
     * Updates an existing customer. Start from `$customer->edit()`.
     *
     * @throws InvalidArgumentException When the input has no code.
     * @throws DimensionNotFoundException When there is no customer with this code.
     * @throws ProcessXmlException When Twinfield refuses the changes.
     */
    public function update(CustomerInput $customer): Customer
    {
        if ($customer->code === null) {
            throw new InvalidArgumentException('Updating a customer needs its code: start from $customer->edit().');
        }

        $this->get($customer->code);

        return $this->save($customer);
    }

    /**
     * Creates the customer, or updates it when the code exists (one request, no check).
     *
     * @throws ProcessXmlException When Twinfield refuses the customer.
     */
    public function save(CustomerInput $customer): Customer
    {
        $xml = $customer->toXml($this->requests->company());

        return Customer::fromXml($this->requests->save($xml));
    }

    /**
     * Deletes the customer. When it is used in a transaction, Twinfield hides it instead.
     *
     * @throws DimensionNotFoundException When there is no customer with this code.
     */
    public function delete(string $code): DeleteResult
    {
        return $this->requests->delete($code);
    }

    /**
     * Makes a deleted or hidden customer active again.
     *
     * @throws DimensionNotFoundException When there is no customer with this code.
     */
    public function activate(string $code): void
    {
        $this->requests->activate($code);
    }
}
