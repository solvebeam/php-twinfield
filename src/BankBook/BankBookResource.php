<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankBook;

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
 * Bank books of the company, via the BankBookService (a Command & Query web service).
 *
 *   $bankBooks = $twinfield->company('1000')->bankBooks();
 *
 *   foreach ($bankBooks->search()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $bankBook = $bankBooks->get('ING');
 *
 *   $bankBooks->create(BankBookInput::create('ABN', 'ABN AMRO')->generalLedgerAccount('1120'));
 *   $bankBooks->update($bankBook->edit()->name('ING zakelijk'));
 *
 * Twinfield has no command to delete a bank book. Cash books are not bank books: get()
 * throws BankBookNotFoundException for them, and search() leaves them out. Use
 * `$twinfield->cashBooks()` for cash books.
 *
 * Other errors are thrown as CommandQueryFaultException; `errorCode` holds a BankBookServiceFaultCode value.
 *
 * Field order in the commands follows the WSDL; the service does not accept another order.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/bank-book/
 */
final readonly class BankBookResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search bank books by code and name (Finder type BNK, without cash books),
     * e.g. `search('ING*')->limit(10)->get()`.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<BankBookSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        // banktype 0: bank books only; -1 adds cash books, 1 gives only cash books.
        $query = $this->twinfield->finder(FinderType::CashbooksAndBanks)->option('banktype', '0');

        return (new CodeNameSearch($query, BankBookSummary::fromFinderRow(...)))->pattern($pattern);
    }

    /**
     * @throws BankBookNotFoundException When there is no bank book with this code.
     */
    public function get(string $code): BankBook
    {
        $company = $this->company();

        try {
            $result = $this->twinfield->query(
                'BankBookService',
                new Query('GetBankBook', ['Code' => $code]),
                'GetBankBookResult',
            );
        } catch (CommandQueryFaultException $exception) {
            throw $this->map($exception, $code);
        }

        return BankBook::fromResult($result, $company, $code);
    }

    /**
     * The bank book, or null when there is no bank book with this code.
     */
    public function find(string $code): ?BankBook
    {
        try {
            return $this->get($code);
        } catch (BankBookNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new bank book with one command, then reads it.
     *
     * @throws BankBookAlreadyExistsException When the code is already in use.
     */
    public function create(BankBookInput $bankBook): BankBook
    {
        $this->process($bankBook->toCreateCommand(), $bankBook->code);

        return $this->get($bankBook->code);
    }

    /**
     * Updates an existing bank book to the values of the input. Start from `$bankBook->edit()`
     * to keep the values you do not change.
     *
     * Reads the bank book first and sends only the change commands for values that differ
     * (name, short name, bank account, general ledger account), so an unchanged account
     * number or ledger account never triggers a "may not be changed" error. Nothing changed
     * means no command. The commands are separate requests: when one fails, the earlier
     * ones are already done.
     *
     * @throws BankBookNotFoundException When there is no bank book with this code.
     */
    public function update(BankBookInput $bankBook): BankBook
    {
        $current = $this->get($bankBook->code);
        $code = $bankBook->code;
        $changed = false;

        if ($bankBook->name !== $current->name) {
            $this->rename($code, $bankBook->name);
            $changed = true;
        }

        if ($bankBook->shortName !== $current->shortName) {
            $this->changeShortName($code, $bankBook->shortName);
            $changed = true;
        }

        if ($bankBook->bankAccount != $current->bankAccount->edit()) {
            $this->changeBankAccount($code, $bankBook->bankAccount);
            $changed = true;
        }

        if ($bankBook->generalLedgerAccount !== $current->generalLedgerAccount) {
            $this->changeGeneralLedgerAccount($code, $bankBook->generalLedgerAccount);
            $changed = true;
        }

        return $changed ? $this->get($code) : $current;
    }

    /**
     * Changes the name (ChangeBankBookName).
     */
    public function rename(string $code, string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Bank book name must not be empty.');
        }

        $this->process(new Command('ChangeBankBookName', ['Code' => $code, 'Name' => $name]), $code);
    }

    /**
     * Changes the short name (ChangeBankBookShortName); null removes it.
     */
    public function changeShortName(string $code, ?string $shortName): void
    {
        $shortName = $shortName === '' ? null : $shortName;

        $this->process(new Command('ChangeBankBookShortName', [
            'Code' => $code,
            'ShortName' => $shortName ?? Nil::Value,
        ]), $code);
    }

    /**
     * Replaces the bank account with all fields of the input (ChangeBankBookBankAccount).
     * Twinfield refuses a new account number once the bank book is used
     * (BankBookServiceFaultCode::AccountNumberMayNotBeChanged).
     */
    public function changeBankAccount(string $code, BankAccountInput $bankAccount): void
    {
        $this->process(new Command('ChangeBankBookBankAccount', [
            'Code' => $code,
            'BankAccount' => $bankAccount->toFields(),
        ]), $code);
    }

    /**
     * Changes the general ledger account (ChangeBankBookGeneralLedgerAccount); null removes it.
     * Twinfield refuses this once the bank book is used
     * (BankBookServiceFaultCode::GeneralLedgerAccountMayNotBeChanged).
     */
    public function changeGeneralLedgerAccount(string $code, ?string $account): void
    {
        $account = $account === '' ? null : $account;

        $this->process(new Command('ChangeBankBookGeneralLedgerAccount', [
            'Code' => $code,
            'GeneralLedgerAccount' => $account ?? Nil::Value,
        ]), $code);
    }

    private function process(Command $command, string $code): void
    {
        try {
            $this->twinfield->process('BankBookService', $command);
        } catch (CommandQueryFaultException $exception) {
            throw $this->map($exception, $code);
        }
    }

    /**
     * Not found and already exists get their own exception; other faults are thrown as they are.
     */
    private function map(CommandQueryFaultException $exception, string $code): RuntimeException
    {
        if ($exception->type !== 'BankBookServiceFault') {
            return $exception;
        }

        $reason = $exception->errorMessage;

        return match (BankBookServiceFaultCode::tryFrom($exception->errorCode)) {
            BankBookServiceFaultCode::NotFound => new BankBookNotFoundException($code, $reason, $exception),
            BankBookServiceFaultCode::AlreadyExists => new BankBookAlreadyExistsException($code, $reason, $exception),
            default => $exception,
        };
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Bank books belong to a company: use company() first.');
    }
}
