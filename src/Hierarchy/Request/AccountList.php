<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy\Request;

use SolveBeam\Twinfield\Hierarchy\HierarchyAccount;
use SolveBeam\Twinfield\Soap\SoapArray;

/**
 * `ArrayOfHierarchyAccount`.
 *
 * @internal
 */
#[SoapArray(elementName: 'HierarchyAccount')]
final readonly class AccountList
{
    /**
     * @param list<AccountBody> $items
     */
    public function __construct(
        public array $items,
    ) {
    }

    public static function of(HierarchyAccount ...$accounts): self
    {
        return new self(array_values(array_map(
            static fn (HierarchyAccount $account): AccountBody => new AccountBody(
                $account->type->value,
                $account->code,
                $account->balanceType->value,
            ),
            $accounts,
        )));
    }
}
