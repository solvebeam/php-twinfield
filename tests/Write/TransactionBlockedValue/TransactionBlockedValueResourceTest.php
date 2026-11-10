<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\TransactionBlockedValue;

use DateTimeImmutable;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\PurchaseTransaction\PurchaseTransactionInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\VatCode\VatCode;
use SolveBeam\Twinfield\VatCode\VatCodeType;

/**
 * Creates a provisional purchase invoice in day book INK, registers a blocked value on its total
 * line, unregisters it and deletes the invoice again. Never posts: a final transaction cannot be
 * deleted.
 *
 * Uses the first supplier, the first profit and loss account and the first purchase VAT code of
 * the company.
 */
final class TransactionBlockedValueResourceTest extends WriteTestCase
{
    public function testRegistersAndUnregistersABlockedValue(): void
    {
        $purchases = $this->twinfield->purchaseTransactions();
        $blocked = $this->twinfield->transactionBlockedValues();
        $date = new DateTimeImmutable('today');

        $created = $purchases->create(
            PurchaseTransactionInput::invoice('INK', $this->supplier(), 'SDK-B-' . date('YmdHis'), $date)
                ->dueDate($date->modify('+30 days'))
                ->expense(
                    $this->profitAndLossAccount(),
                    '10.00',
                    vatCode: $this->vatCode(),
                    description: 'PHP SDK write test',
                ),
        );

        try {
            $blocked->register('INK', $created->number(), $created->total->id, '5.00');
            $blocked->unregister('INK', $created->number(), $created->total->id);

            $this->addToAssertionCount(1);
        } finally {
            $purchases->delete('INK', $created->number(), 'PHP SDK write test');
        }
    }

    private function supplier(): string
    {
        return $this->twinfield->suppliers()->search()->limit(1)->get()->items[0]->code
            ?? self::markTestSkipped('The company has no suppliers.');
    }

    private function profitAndLossAccount(): string
    {
        $row = $this->twinfield->finder(FinderType::Dimensions)
            ->option('dimtype', 'PNL')
            ->limit(1)
            ->get()
            ->rows[0] ?? null;

        if ($row === null) {
            self::markTestSkipped('The company has no profit and loss accounts.');
        }

        return $row[0];
    }

    private function vatCode(): VatCode
    {
        $code = $this->twinfield->vatCodes()->search()->vatType(VatCodeType::Purchase)->get()->items[0]->code
            ?? self::markTestSkipped('The company has no purchase VAT code.');

        return $this->twinfield->vatCodes()->get($code);
    }
}
