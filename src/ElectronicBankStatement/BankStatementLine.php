<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A line of a bank statement as Twinfield keeps it, with how Twinfield allocated it.
 *
 * Text fields are null when Twinfield gives them empty or nil; banks often leave out the
 * account number (BBAN) of the other party, the payment reference or the end-to-end ID.
 */
final readonly class BankStatementLine
{
    /**
     * @param list<BankStatementAllocationMethod> $allocationMethods Conditions of the allocation rule;
     *                                                               empty when none was used.
     * @param list<BankStatementAllocation> $allocations
     */
    public function __construct(
        /** Line number within the statement, e.g. `1`. */
        public int $lineId,
        /** Amount of the line: positive for money received, negative for money paid. */
        public Amount $amount,
        /** Amount in the base currency of the company, with the same sign. */
        public Amount $baseAmount,
        public ?string $contraAccountNumber,
        public ?string $contraIban,
        public ?string $contraAccountName,
        public ?string $paymentReference,
        public ?string $description,
        /** Transaction type code of the bank, e.g. `N541`. */
        public ?string $transactionTypeId,
        public ?string $reference,
        public ?string $endToEndId,
        public ?string $returnReason,
        /** How Twinfield recognised the line; null when it was not recognised automatically. */
        public ?BankStatementAllocationRecognition $recognition,
        public array $allocationMethods,
        public array $allocations,
        public BankStatementAllocationRuleStorageLevel $allocationRuleStorageLevel,
    ) {
    }

    public static function fromResult(ResultElement $line): self
    {
        return new self(
            lineId: $line->int('LineId'),
            amount: $line->amount('Amount'),
            baseAmount: $line->amount('BaseAmount'),
            contraAccountNumber: $line->nullableString('ContraAccountNumber'),
            contraIban: $line->nullableString('ContraIban'),
            contraAccountName: $line->nullableString('ContraAccountName'),
            paymentReference: $line->nullableString('PaymentReference'),
            description: $line->nullableString('Description'),
            transactionTypeId: $line->nullableString('TransactionTypeId'),
            reference: $line->nullableString('Reference'),
            endToEndId: $line->nullableString('EndToEndId'),
            returnReason: $line->nullableString('ReturnReason'),
            recognition: $line->nullableEnum('Recognition', BankStatementAllocationRecognition::class),
            allocationMethods: array_map(
                BankStatementAllocationMethod::fromResult(...),
                $line->list('AllocationMethods', 'BankStatementAllocationMethod'),
            ),
            allocations: array_map(
                BankStatementAllocation::fromResult(...),
                $line->list('Allocations', 'BankStatementAllocation'),
            ),
            allocationRuleStorageLevel: $line->enum(
                'AllocationRuleStorageLevel',
                BankStatementAllocationRuleStorageLevel::class,
            ),
        );
    }
}
