<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Supplier;

use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Dimension\RemittanceAdviceSendType;
use SolveBeam\Twinfield\Supplier\MeansOfPayment;
use SolveBeam\Twinfield\Supplier\SupplierInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates a temporary supplier with the next free code, changes how it is paid, its
 * remittance advice and bank accounts, deletes and reactivates it; always deletes it again.
 */
final class SupplierResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $suppliers = $this->twinfield->suppliers();
        $code = null;

        try {
            $created = $suppliers->create(
                SupplierInput::create('SDK test supplier')
                    ->dueDays(21)
                    ->relationsReference('SDK-TEST')
                    ->cheques()
                    ->remittanceAdvice(RemittanceAdviceSendType::ByEmail, 'remittance@example.com')
                    ->address(
                        AddressInput::invoice('SDK test supplier')
                            ->field(2, 'Main street 1')
                            ->city('Amsterdam')
                            ->country('NL')
                            ->default(),
                    )
                    ->bank(BankInput::create()->iban('NL91ABNA0417164300')->ascription('SDK test supplier')->default()),
            );
            $code = $created->code;

            self::assertSame(DimensionStatus::Active, $created->status);
            self::assertFalse($created->inUse);
            self::assertSame(21, $created->financials->dueDays);

            $read = $suppliers->get($code);

            self::assertSame('SDK test supplier', $read->name);
            self::assertSame($created->uid, $read->uid);
            self::assertSame('SDK-TEST', $read->financials->relationsReference);
            self::assertSame(MeansOfPayment::Cheques, $read->financials->meansOfPayment);
            self::assertTrue($read->financials->payAvailable);
            self::assertSame(RemittanceAdviceSendType::ByEmail, $read->remittanceAdvice?->sendType);
            self::assertSame('remittance@example.com', $read->remittanceAdvice->email);
            self::assertSame('NL91ABNA0417164300', $read->defaultBank()?->iban);

            try {
                $suppliers->create(SupplierInput::create('Duplicate')->code($code));
                self::fail('Expected a DimensionAlreadyExistsException.');
            } catch (DimensionAlreadyExistsException) {
                self::assertSame(
                    'SDK test supplier',
                    $suppliers->get($code)->name,
                    'The existing supplier is unchanged.',
                );
            }

            // No payments by Twinfield, no external number, and a second bank account.
            $updated = $suppliers->update(
                $read->edit()
                    ->name('SDK test supplier (updated) & more')
                    ->withoutPayments()
                    ->relationsReference(null)
                    ->remittanceAdvice(RemittanceAdviceSendType::ToFileManager)
                    ->bank(BankInput::create()->iban('BE68539007547034')->ascription('SDK test supplier')),
            );

            self::assertSame('SDK test supplier (updated) & more', $updated->name);

            $read = $suppliers->get($code);

            self::assertSame(MeansOfPayment::None, $read->financials->meansOfPayment);
            self::assertFalse($read->financials->payAvailable);
            self::assertNull($read->financials->relationsReference);
            self::assertSame(RemittanceAdviceSendType::ToFileManager, $read->remittanceAdvice?->sendType);
            self::assertCount(2, $read->banks);
            self::assertSame('NL91ABNA0417164300', $read->defaultBank()?->iban, 'The default stays the default.');

            // Remove the second bank account.
            $second = $read->banks[1] ?? self::fail('Expected a second bank account.');
            $suppliers->update($read->edit()->withoutBank($second->id));

            self::assertCount(1, $suppliers->get($code)->banks, 'A bank account left out of an update is removed.');

            self::assertSame(DeleteResult::Deleted, $suppliers->delete($code));
            self::assertSame(DimensionStatus::Deleted, $suppliers->get($code)->status);

            $suppliers->activate($code);
            self::assertSame(DimensionStatus::Active, $suppliers->get($code)->status);
        } finally {
            if ($code !== null && $suppliers->find($code)?->status === DimensionStatus::Active) {
                $suppliers->delete($code);
            }
        }
    }
}
