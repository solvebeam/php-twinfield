<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\SalesInvoice;

use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceNotFoundException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * Live sales invoice reads and invoice type searches (read-only) against TWINFIELD_COMPANY.
 *
 * Set TWINFIELD_SALES_INVOICES to existing invoices, e.g. `FACTUUR:1,FACTUUR:5`, to also read
 * them: there is no search for sales invoices.
 */
final class SalesInvoiceResourceTest extends IntegrationTestCase
{
    public function testThrowsNotFoundForAnUnknownNumber(): void
    {
        $types = $this->twinfield->salesInvoices()->invoiceTypes()->limit(1)->get();

        if ($types->items === []) {
            self::markTestSkipped('The company has no invoice types.');
        }

        $this->expectException(SalesInvoiceNotFoundException::class);

        $this->twinfield->salesInvoices()->get($types->items[0]->code, 999999999);
    }

    public function testSearchesInvoiceTypes(): void
    {
        $page = $this->twinfield->salesInvoices()->invoiceTypes()->limit(20)->get();

        if ($page->items === []) {
            self::markTestSkipped('The company has no invoice types.');
        }

        foreach ($page->items as $type) {
            self::assertNotSame('', $type->code);
            self::assertNotSame('', $type->name);
            self::assertSame($this->twinfield->companyCode(), $type->company->code);
        }
    }

    public function testReadsSalesInvoices(): void
    {
        $setting = $this->environment->config->get('TWINFIELD_SALES_INVOICES');

        if ($setting === null) {
            self::markTestSkipped('Set TWINFIELD_SALES_INVOICES, e.g. FACTUUR:1,FACTUUR:5, to read sales invoices.');
        }

        $invoices = $this->twinfield->salesInvoices();

        foreach (explode(',', $setting) as $reference) {
            [$type, $number] = explode(':', trim($reference)) + [1 => ''];

            $invoice = $invoices->get($type, (int) $number);

            self::assertSame($type, $invoice->invoiceType);
            self::assertSame((int) $number, $invoice->invoiceNumber);

            // The totals are the sum of the lines.
            $excl = Amount::sum(array_map(static fn ($line): Amount => $line->valueExcl, $invoice->lines));
            $inc = Amount::sum(array_map(static fn ($line): Amount => $line->valueInc, $invoice->lines));
            self::assertSame($invoice->totals->valueExcl->value, $excl->value);
            self::assertSame($invoice->totals->valueInc->value, $inc->value);

            if (!$invoice->isFinal()) {
                $input = $invoice->edit();
                self::assertSame($invoice->invoiceNumber, $input->invoiceNumber);
                self::assertCount(count($invoice->lines), $input->lines);

                continue;
            }

            // A final invoice links to the sales transaction Twinfield booked.
            $transaction = $invoices->salesTransaction($invoice);
            self::assertSame((string) $invoice->invoiceNumber, $transaction->invoiceNumber);
            self::assertSame($invoice->totals->valueInc->value, $transaction->value()->value);
        }
    }
}
