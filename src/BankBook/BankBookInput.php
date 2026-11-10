<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankBook;

use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A bank book to create or update. Immutable: every method returns a new input.
 *
 *   $input = BankBookInput::create('ABN', 'ABN AMRO')
 *       ->shortName('ABN')
 *       ->generalLedgerAccount('1120')
 *       ->bankAccount(BankAccountInput::create()->iban('NL91ABNA0417164300')->name('Example B.V.'));
 *
 * For an existing bank book, start from `$bankBook->edit()`, so all current values are kept.
 */
final readonly class BankBookInput
{
    private function __construct(
        public string $code,
        public string $name,
        public ?string $shortName,
        public ?string $generalLedgerAccount,
        public BankAccountInput $bankAccount,
    ) {
        XmlValue::code($code);

        if ($name === '') {
            throw new InvalidArgumentException('Bank book name must not be empty.');
        }
    }

    /**
     * @param string $code Code of the bank book (a transaction type), e.g. `ING`.
     */
    public static function create(string $code, string $name): self
    {
        return new self($code, $name, null, null, BankAccountInput::create());
    }

    public function name(string $name): self
    {
        return new self($this->code, $name, $this->shortName, $this->generalLedgerAccount, $this->bankAccount);
    }

    /**
     * Null removes it.
     */
    public function shortName(?string $shortName): self
    {
        $shortName = $shortName === '' ? null : $shortName;

        return new self($this->code, $this->name, $shortName, $this->generalLedgerAccount, $this->bankAccount);
    }

    /**
     * Balance sheet account of the bank, e.g. `1100`. Twinfield refuses to change it once it is
     * used (BankBookServiceFaultCode::GeneralLedgerAccountMayNotBeChanged).
     */
    public function generalLedgerAccount(?string $account): self
    {
        $account = $account === '' ? null : $account;

        return new self($this->code, $this->name, $this->shortName, $account, $this->bankAccount);
    }

    public function bankAccount(BankAccountInput $bankAccount): self
    {
        return new self($this->code, $this->name, $this->shortName, $this->generalLedgerAccount, $bankAccount);
    }

    /**
     * The `CreateBankBook` command, fields in WSDL order. Empty optional fields are left out.
     *
     * @internal
     */
    public function toCreateCommand(): Command
    {
        return new Command('CreateBankBook', [
            'Code' => $this->code,
            'BankAccount' => $this->bankAccount->isEmpty() ? null : $this->bankAccount->toFields(),
            'GeneralLedgerAccount' => $this->generalLedgerAccount,
            'Name' => $this->name,
            'ShortName' => $this->shortName,
        ]);
    }
}
