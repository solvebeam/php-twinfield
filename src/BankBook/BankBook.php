<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankBook;

use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A bank book as Twinfield returns it (BankBookService GetBankBook).
 *
 *   $bankBook = $twinfield->bankBooks()->get('ING');
 *   echo $bankBook->name, ' ', $bankBook->bankAccount->iban, PHP_EOL;
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/bank-book/
 */
final readonly class BankBook
{
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Code of the bank book (a transaction type), e.g. `ING`. */
        public string $code,
        public string $name,
        /** Null when not set. */
        public ?string $shortName,
        /** Balance sheet account, e.g. `1100`. Null when not set, as on the default bank book `BNK` of a new company. */
        public ?string $generalLedgerAccount,
        public BankAccount $bankAccount,
        public AutomaticBankProcessing $automaticBankProcessing,
    ) {
        foreach (['code' => $code, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Bank book %s must not be empty.', $field));
            }
        }
    }

    /**
     * From a `GetBankBookResult`. The result has no code: it is the code of the request.
     */
    public static function fromResult(ResultElement $result, CompanyReference $company, string $code): self
    {
        return new self(
            company: $company,
            code: $code,
            name: $result->string('Name'),
            // Empty elements when not set.
            shortName: $result->nullableString('ShortName'),
            generalLedgerAccount: $result->nullableString('GeneralLedgerAccount'),
            bankAccount: BankAccount::fromResult($result->child('BankAccount')),
            automaticBankProcessing: AutomaticBankProcessing::fromResult($result->child('AutomaticBankProcessing')),
        );
    }

    /**
     * Input with all values of this bank book, to change and pass to update().
     *
     *   $bankBooks->update($bankBook->edit()->name('ING zakelijk'));
     */
    public function edit(): BankBookInput
    {
        return BankBookInput::create($this->code, $this->name)
            ->shortName($this->shortName)
            ->generalLedgerAccount($this->generalLedgerAccount)
            ->bankAccount($this->bankAccount->edit());
    }
}
