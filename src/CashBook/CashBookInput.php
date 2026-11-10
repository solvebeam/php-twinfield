<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashBook;

use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A cash book to create or update. Immutable: every method returns a new input.
 *
 *   $input = CashBookInput::create('KAS2', 'Kas winkel')
 *       ->shortName('Winkel')
 *       ->generalLedgerAccount('1010');
 *
 * For an existing cash book, start from `$cashBook->edit()`, so all current values are kept.
 */
final readonly class CashBookInput
{
    private function __construct(
        public string $code,
        public string $name,
        public ?string $shortName,
        public ?string $generalLedgerAccount,
    ) {
        XmlValue::code($code);

        if ($name === '') {
            throw new InvalidArgumentException('Cash book name must not be empty.');
        }
    }

    /**
     * @param string $code Code of the cash book (a transaction type), e.g. `KAS`.
     */
    public static function create(string $code, string $name): self
    {
        return new self($code, $name, null, null);
    }

    public function name(string $name): self
    {
        return new self($this->code, $name, $this->shortName, $this->generalLedgerAccount);
    }

    /**
     * Null removes it.
     */
    public function shortName(?string $shortName): self
    {
        $shortName = $shortName === '' ? null : $shortName;

        return new self($this->code, $this->name, $shortName, $this->generalLedgerAccount);
    }

    /**
     * Balance sheet account of the cash, e.g. `1000`. Twinfield refuses to change it once it is
     * used (CashBookServiceFaultCode::GeneralLedgerAccountMayNotBeChanged).
     */
    public function generalLedgerAccount(?string $account): self
    {
        $account = $account === '' ? null : $account;

        return new self($this->code, $this->name, $this->shortName, $account);
    }

    /**
     * The `CreateCashBook` command, fields in WSDL order. Empty optional fields are left out.
     *
     * @internal
     */
    public function toCreateCommand(): Command
    {
        return new Command('CreateCashBook', [
            'Code' => $this->code,
            'GeneralLedgerAccount' => $this->generalLedgerAccount,
            'Name' => $this->name,
            'ShortName' => $this->shortName,
        ]);
    }
}
