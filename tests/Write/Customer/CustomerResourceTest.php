<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Customer;

use SolveBeam\Twinfield\Customer\CustomerInput;
use SolveBeam\Twinfield\Customer\SendReminder;
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\AddressType;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates a temporary customer with the next free code, changes its addresses and bank
 * accounts, deletes and reactivates it; always deletes it again.
 *
 * This also confirms how Twinfield handles lists: an address or bank account left out of an
 * update is removed.
 */
final class CustomerResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $customers = $this->twinfield->customers();
        $code = null;

        try {
            $created = $customers->create(
                CustomerInput::create('SDK test customer')
                    ->dueDays(21)
                    ->sendReminder(SendReminder::ByEmail, 'reminders@example.com')
                    ->address(
                        AddressInput::invoice('SDK test customer')
                            ->field(2, 'Main street 1')
                            ->city('Amsterdam')
                            ->country('NL')
                            ->default(),
                    )
                    ->bank(BankInput::create()->iban('NL91ABNA0417164300')->ascription('SDK test customer')->default()),
            );
            $code = $created->code;

            self::assertSame(DimensionStatus::Active, $created->status);
            self::assertFalse($created->inUse);
            self::assertSame(21, $created->financials->dueDays);

            $read = $customers->get($code);

            self::assertSame('SDK test customer', $read->name);
            self::assertSame($created->uid, $read->uid);
            self::assertSame(SendReminder::ByEmail, $read->creditManagement->sendReminder);
            self::assertSame('reminders@example.com', $read->creditManagement->reminderEmail);
            self::assertCount(1, $read->addresses);
            $defaultAddress = $read->defaultAddress();

            self::assertNotNull($defaultAddress);
            self::assertSame('Amsterdam', $defaultAddress->city);
            self::assertSame('NL91ABNA0417164300', $read->defaultBank()?->iban);

            try {
                $customers->create(CustomerInput::create('Duplicate')->code($code));
                self::fail('Expected a DimensionAlreadyExistsException.');
            } catch (DimensionAlreadyExistsException) {
                self::assertSame(
                    'SDK test customer',
                    $customers->get($code)->name,
                    'The existing customer is unchanged.',
                );
            }

            // Change the invoice address, add a postal address and a second bank account.
            $invoice = $defaultAddress->edit();
            $updated = $customers->update(
                $read->edit()
                    ->name('SDK test customer (updated) & more')
                    ->dueDays(14)
                    ->address($invoice->city('Rotterdam'))
                    ->address(
                        AddressInput::postal('SDK test customer')->field(2, 'Postbus 1')->city('Utrecht')->country(
                            'NL',
                        ),
                    )
                    ->bank(BankInput::create()->iban('BE68539007547034')->ascription('SDK test customer')),
            );

            self::assertSame('SDK test customer (updated) & more', $updated->name);
            self::assertSame(14, $updated->financials->dueDays);

            $read = $customers->get($code);

            self::assertCount(2, $read->addresses);
            self::assertSame('Rotterdam', $read->defaultAddress()?->city);
            self::assertSame('Utrecht', $read->addressesOfType(AddressType::Postal)[0]->city ?? null);
            self::assertCount(2, $read->banks);
            self::assertSame('NL91ABNA0417164300', $read->defaultBank()?->iban, 'The default stays the default.');

            // Remove the postal address and the second bank account.
            $postal = $read->addressesOfType(AddressType::Postal)[0] ?? self::fail('Expected a postal address.');
            $second = $read->banks[1] ?? self::fail('Expected a second bank account.');

            $customers->update($read->edit()->withoutAddress($postal->id)->withoutBank($second->id));

            $read = $customers->get($code);

            self::assertCount(1, $read->addresses, 'An address left out of an update is removed.');
            self::assertCount(1, $read->banks, 'A bank account left out of an update is removed.');

            self::assertSame(DeleteResult::Deleted, $customers->delete($code));
            self::assertSame(DimensionStatus::Deleted, $customers->get($code)->status);

            $customers->activate($code);
            self::assertSame(DimensionStatus::Active, $customers->get($code)->status);
        } finally {
            if ($code !== null && $customers->find($code)?->status === DimensionStatus::Active) {
                $customers->delete($code);
            }
        }
    }
}
