<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\GeneralLedgerAccount;

use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Dimension\DimensionRequests;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Twinfield;

/**
 * General ledger accounts of one type in the company: balance sheet accounts (`BAS`) or
 * profit and loss accounts (`PNL`), the first dimension (`dim1`) of transaction lines.
 *
 *   $accounts = $twinfield->company('1000')->profitAndLossAccounts();
 *
 *   foreach ($accounts->search('8*')->inCode()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $account = $accounts->get('8000');
 *
 *   $accounts->create(
 *       GeneralLedgerAccountInput::profitAndLoss('8010', 'Revenue services')
 *           ->vatCode('VH')
 *           ->subAnalysis(SubAnalysis::Required)
 *           ->childValidation(ChildValidation::dimensionType('KPL')),
 *   );
 *
 *   $accounts->update($account->edit()->name('Revenue products'));
 *   $accounts->delete('8010');
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/general-ledger-accounts/
 */
final readonly class GeneralLedgerAccountResource
{
    private DimensionRequests $requests;

    public function __construct(Twinfield $twinfield, private GeneralLedgerAccountType $type)
    {
        // E.g. "Profit and loss account" and "Profit and loss accounts" in messages.
        $label = ucfirst($type->label());
        $this->requests = new DimensionRequests($twinfield, $type->value, $label, $label . 's');
    }

    /**
     * BAS or PNL.
     */
    public function type(): GeneralLedgerAccountType
    {
        return $this->type;
    }

    /**
     * Search accounts by code and name, e.g. `search('8*')->inCode()->get()`, or by dimension
     * group: `search()->group('REVENUE')`. With `includeHidden()` also hidden accounts.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<GeneralLedgerAccountSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        return $this->requests->search($pattern, GeneralLedgerAccountSummary::fromFinderRow(...));
    }

    /**
     * @throws DimensionNotFoundException When there is no account of this type with this code.
     */
    public function get(string $code): GeneralLedgerAccount
    {
        return GeneralLedgerAccount::fromXml($this->requests->read($code), $this->requests->organisation());
    }

    /**
     * The account, or null when there is no account of this type with this code.
     */
    public function find(string $code): ?GeneralLedgerAccount
    {
        try {
            return $this->get($code);
        } catch (DimensionNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new account. Reads first, so an existing account is never overwritten.
     *
     * @throws InvalidArgumentException When the input is of the other type.
     * @throws DimensionAlreadyExistsException When the code is already in use.
     * @throws ProcessXmlException When Twinfield refuses the account.
     */
    public function create(GeneralLedgerAccountInput $account): GeneralLedgerAccount
    {
        $this->assertType($account);

        if ($this->find($account->code) !== null) {
            throw new DimensionAlreadyExistsException($this->type->value, $account->code);
        }

        return $this->save($account);
    }

    /**
     * Updates an existing account. Start from `$account->edit()`.
     *
     * @throws InvalidArgumentException When the input is of the other type.
     * @throws DimensionNotFoundException When there is no account of this type with this code.
     * @throws ProcessXmlException When Twinfield refuses the changes.
     */
    public function update(GeneralLedgerAccountInput $account): GeneralLedgerAccount
    {
        $this->assertType($account);
        $this->get($account->code);

        return $this->save($account);
    }

    /**
     * Creates the account, or updates it when the code exists (one request, no check).
     *
     * @throws InvalidArgumentException When the input is of the other type.
     * @throws ProcessXmlException When Twinfield refuses the account.
     */
    public function save(GeneralLedgerAccountInput $account): GeneralLedgerAccount
    {
        $this->assertType($account);

        $xml = $account->toXml($this->requests->company());

        return GeneralLedgerAccount::fromXml($this->requests->save($xml), $this->requests->organisation());
    }

    /**
     * Deletes the account. When it is in use (a transaction, VAT code or article), Twinfield
     * hides it instead.
     *
     * @throws DimensionNotFoundException When there is no account of this type with this code.
     */
    public function delete(string $code): DeleteResult
    {
        return $this->requests->delete($code);
    }

    /**
     * Makes a deleted or hidden account active again.
     *
     * @throws DimensionNotFoundException When there is no account of this type with this code.
     */
    public function activate(string $code): void
    {
        $this->requests->activate($code);
    }

    private function assertType(GeneralLedgerAccountInput $account): void
    {
        if ($account->type !== $this->type) {
            throw new InvalidArgumentException(sprintf(
                'Account %s is a %s (%s), but this resource is for %ss (%s).',
                $account->code,
                $account->type->label(),
                $account->type->value,
                $this->type->label(),
                $this->type->value,
            ));
        }
    }
}
