<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashBook;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\CommandQuery\Nil;
use SolveBeam\Twinfield\CommandQuery\Query;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Cash books of the company, via the CashBookService (a Command & Query web service).
 *
 *   $cashBooks = $twinfield->company('1000')->cashBooks();
 *
 *   foreach ($cashBooks->search()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $cashBook = $cashBooks->get('KAS');
 *
 *   $cashBooks->create(CashBookInput::create('KAS2', 'Kas winkel')->generalLedgerAccount('1010'));
 *   $cashBooks->update($cashBook->edit()->name('Kas hoofdkantoor'));
 *
 * Twinfield has no command to delete a cash book. Bank books have their own service: see
 * `$twinfield->bankBooks()`. To book on a cash book, use `$twinfield->cashTransactions()`.
 *
 * Other errors are thrown as CommandQueryFaultException; `errorCode` holds a CashBookServiceFaultCode value.
 *
 * Field order in the commands follows the WSDL; the service does not accept another order.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/cash-book/
 */
final readonly class CashBookResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search cash books by code and name (Finder type BNK, only cash books),
     * e.g. `search('KAS*')->limit(10)->get()`.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<CashBookSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        // banktype 1: cash books only; 0 gives only bank books, -1 both.
        $query = $this->twinfield->finder(FinderType::CashbooksAndBanks)->option('banktype', '1');

        return (new CodeNameSearch($query, CashBookSummary::fromFinderRow(...)))->pattern($pattern);
    }

    /**
     * @throws CashBookNotFoundException When there is no cash book with this code.
     */
    public function get(string $code): CashBook
    {
        $company = $this->company();

        try {
            $result = $this->twinfield->query(
                'CashBookService',
                new Query('GetCashBook', ['Code' => $code]),
                'GetCashBookResult',
            );
        } catch (CommandQueryFaultException $exception) {
            throw $this->map($exception, $code);
        }

        return CashBook::fromResult($result, $company, $code);
    }

    /**
     * The cash book, or null when there is no cash book with this code.
     */
    public function find(string $code): ?CashBook
    {
        try {
            return $this->get($code);
        } catch (CashBookNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new cash book with one command, then reads it.
     *
     * @throws CashBookAlreadyExistsException When the code is already in use.
     */
    public function create(CashBookInput $cashBook): CashBook
    {
        $this->process($cashBook->toCreateCommand(), $cashBook->code);

        return $this->get($cashBook->code);
    }

    /**
     * Updates an existing cash book to the values of the input. Start from `$cashBook->edit()`
     * to keep the values you do not change.
     *
     * Reads the cash book first and sends only the change commands for values that differ
     * (name, short name, general ledger account), so an unchanged ledger account never
     * triggers a "may not be changed" error. Nothing changed means no command. The commands
     * are separate requests: when one fails, the earlier ones are already done.
     *
     * @throws CashBookNotFoundException When there is no cash book with this code.
     */
    public function update(CashBookInput $cashBook): CashBook
    {
        $current = $this->get($cashBook->code);
        $code = $cashBook->code;
        $changed = false;

        if ($cashBook->name !== $current->name) {
            $this->rename($code, $cashBook->name);
            $changed = true;
        }

        if ($cashBook->shortName !== $current->shortName) {
            $this->changeShortName($code, $cashBook->shortName);
            $changed = true;
        }

        if ($cashBook->generalLedgerAccount !== $current->generalLedgerAccount) {
            $this->changeGeneralLedgerAccount($code, $cashBook->generalLedgerAccount);
            $changed = true;
        }

        return $changed ? $this->get($code) : $current;
    }

    /**
     * Changes the name (ChangeCashBookName).
     */
    public function rename(string $code, string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Cash book name must not be empty.');
        }

        $this->process(new Command('ChangeCashBookName', ['Code' => $code, 'Name' => $name]), $code);
    }

    /**
     * Changes the short name (ChangeCashBookShortName); null removes it.
     */
    public function changeShortName(string $code, ?string $shortName): void
    {
        $shortName = $shortName === '' ? null : $shortName;

        $this->process(new Command('ChangeCashBookShortName', [
            'Code' => $code,
            'ShortName' => $shortName ?? Nil::Value,
        ]), $code);
    }

    /**
     * Changes the general ledger account (ChangeCashBookGeneralLedgerAccount); null removes it.
     * Twinfield refuses this once the cash book is used
     * (CashBookServiceFaultCode::GeneralLedgerAccountMayNotBeChanged).
     */
    public function changeGeneralLedgerAccount(string $code, ?string $account): void
    {
        $account = $account === '' ? null : $account;

        $this->process(new Command('ChangeCashBookGeneralLedgerAccount', [
            'Code' => $code,
            'GeneralLedgerAccount' => $account ?? Nil::Value,
        ]), $code);
    }

    private function process(Command $command, string $code): void
    {
        try {
            $this->twinfield->process('CashBookService', $command);
        } catch (CommandQueryFaultException $exception) {
            throw $this->map($exception, $code);
        }
    }

    /**
     * Not found and already exists get their own exception; other faults are thrown as they are.
     */
    private function map(CommandQueryFaultException $exception, string $code): RuntimeException
    {
        if ($exception->type !== 'CashBookServiceFault') {
            return $exception;
        }

        $reason = $exception->errorMessage;

        return match (CashBookServiceFaultCode::tryFrom($exception->errorCode)) {
            CashBookServiceFaultCode::NotFound => new CashBookNotFoundException($code, $reason, $exception),
            CashBookServiceFaultCode::AlreadyExists => new CashBookAlreadyExistsException($code, $reason, $exception),
            default => $exception,
        };
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Cash books belong to a company: use company() first.');
    }
}
