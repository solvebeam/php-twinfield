<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use InvalidArgumentException;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountType;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use Stringable;

/**
 * A general ledger account bound to a node of a reporting structure. Also what you pass to
 * bind() and unbind(), and to HierarchyNodeInput::accounts().
 *
 *   HierarchyAccount::balanceSheet('1100');
 *   HierarchyAccount::balanceSheet('1110', HierarchyAccountBalanceType::Debit);
 *   HierarchyAccount::profitAndLoss('8000');
 */
final readonly class HierarchyAccount implements Stringable
{
    public function __construct(
        /** Balance sheet (`BAS`) or profit and loss (`PNL`). */
        public GeneralLedgerAccountType $type,
        /** Code of the general ledger account, e.g. `1100`. */
        public string $code,
        public HierarchyAccountBalanceType $balanceType = HierarchyAccountBalanceType::Balance,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('The code of a general ledger account must not be empty.');
        }
    }

    public static function balanceSheet(
        string $code,
        HierarchyAccountBalanceType $balanceType = HierarchyAccountBalanceType::Balance,
    ): self {
        return new self(GeneralLedgerAccountType::BalanceSheet, $code, $balanceType);
    }

    public static function profitAndLoss(
        string $code,
        HierarchyAccountBalanceType $balanceType = HierarchyAccountBalanceType::Balance,
    ): self {
        return new self(GeneralLedgerAccountType::ProfitAndLoss, $code, $balanceType);
    }

    /**
     * @internal
     */
    public static function fromResponse(XmlElement $account): self
    {
        return new self(
            type: $account->enum('Type', GeneralLedgerAccountType::class),
            code: $account->string('Code'),
            balanceType: $account->enum('BalanceType', HierarchyAccountBalanceType::class),
        );
    }

    /**
     * E.g. `1100 (BAS, Balance)`, for RecursiveTreeIterator.
     */
    public function __toString(): string
    {
        return sprintf('%s (%s, %s)', $this->code, $this->type->value, $this->balanceType->value);
    }
}
