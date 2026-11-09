<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\SalesInvoice;

use DateTimeImmutable;
use SolveBeam\Twinfield\SalesInvoice\PaymentMethod;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceInput;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceStatus;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\VatCode\VatCodeType;

/**
 * Calculates, creates and updates a concept sales invoice with the one-off item `0`.
 *
 * Sales invoices can't be deleted through the API: the concept invoice stays in the company,
 * delete it in Twinfield afterwards. Set TWINFIELD_WRITE_FINAL_INVOICE=1 to also make it
 * final; that books a sales transaction that can't be deleted either.
 *
 * Uses the first invoice type, the first customer and VAT code VH (or the first sales VAT code)
 * of the company.
 */
final class SalesInvoiceResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $invoices = $this->twinfield->salesInvoices();
        $date = new DateTimeImmutable('today');

        $input = SalesInvoiceInput::create($this->invoiceType(), $this->customer())
            ->invoiceDate($date)
            ->dueDate($date->modify('+30 days'))
            ->paymentMethod(PaymentMethod::Bank)
            ->headerText('PHP SDK write test')
            ->oneOff('PHP SDK write test', '1.00', vatCode: $this->vatCode(), quantity: 2);

        $calculation = $invoices->calculate($input);

        self::assertSame('2.00', $calculation->totals->valueExcl->value);
        self::assertCount(1, $calculation->lines);

        $created = $invoices->create($input);

        self::assertSame(SalesInvoiceStatus::Concept, $created->status);
        self::assertSame($calculation->totals->valueInc->value, $created->totals->valueInc->value);
        self::assertSame('PHP SDK write test', $created->lines[0]->description);
        self::assertSame('PHP SDK write test', $created->headerText);
        self::assertTrue($created->lines[0]->isOneOff());
        self::assertNull($created->transaction);

        $read = $invoices->get($created->invoiceType, $created->invoiceNumber);

        self::assertSame($created->invoiceNumber, $read->invoiceNumber);
        self::assertEquals($created->lines, $read->lines);

        $updated = $invoices->update($read->edit()->footerText('PHP SDK write test (updated)'));

        self::assertSame($created->invoiceNumber, $updated->invoiceNumber);
        self::assertSame('PHP SDK write test (updated)', $updated->footerText);
        self::assertCount(1, $updated->lines, 'The line is changed, not added again.');

        if ($this->environment->config->get('TWINFIELD_WRITE_FINAL_INVOICE') !== '1') {
            return;
        }

        $final = $invoices->finalize($created->invoiceType, $created->invoiceNumber);

        self::assertTrue($final->isFinal());
        self::assertNotNull($final->transaction);

        $transaction = $invoices->salesTransaction($final);

        self::assertSame($final->totals->valueInc->value, $transaction->value()->value);
    }

    private function invoiceType(): string
    {
        return $this->twinfield->salesInvoices()->invoiceTypes()->limit(1)->get()->items[0]->code
            ?? self::markTestSkipped('The company has no invoice types.');
    }

    private function customer(): string
    {
        return $this->twinfield->customers()->search()->limit(1)->get()->items[0]->code
            ?? self::markTestSkipped('The company has no customers.');
    }

    private function vatCode(): string
    {
        $codes = array_map(
            static fn ($vatCode): string => $vatCode->code,
            $this->twinfield->vatCodes()->search()->vatType(VatCodeType::Sales)->get()->items,
        );

        if ($codes === []) {
            self::markTestSkipped('The company has no sales VAT code.');
        }

        return in_array('VH', $codes, true) ? 'VH' : $codes[0];
    }
}
