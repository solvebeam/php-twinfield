<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\VatCode;

use DateTimeImmutable;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\VatCode\VatAccount;
use SolveBeam\Twinfield\VatCode\VatAccountInput;
use SolveBeam\Twinfield\VatCode\VatCodeAlreadyExistsException;
use SolveBeam\Twinfield\VatCode\VatCodeInput;
use SolveBeam\Twinfield\VatCode\VatCodeStatus;
use SolveBeam\Twinfield\VatCode\VatCodeType;
use SolveBeam\Twinfield\VatCode\VatPercentageInput;

/**
 * Creates, updates, deletes and reactivates a temporary sales VAT code SDKTEST9xx; always
 * deletes it again. It posts on the accounts of the current percentage of an existing sales
 * VAT code (VH in Dutch companies), so no account has to be created.
 */
final class VatCodeResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $vatCodes = $this->twinfield->vatCodes();
        $code = $this->unusedCode();
        $accounts = array_map(
            static fn (VatAccount $account): VatAccountInput => $account->edit(),
            $this->salesVatCodeAccounts(),
        );

        try {
            $created = $vatCodes->create(
                VatCodeInput::sales($code, 'SDK test VAT code')
                    ->shortName('SDK test')
                    ->percentage(
                        VatPercentageInput::from(new DateTimeImmutable('2025-01-01'), '21')
                            ->name('SDK test 21%')
                            ->withAccounts(...$accounts),
                    ),
            );

            self::assertSame($code, $created->code);
            self::assertSame(VatCodeStatus::Active, $created->status);
            self::assertSame(VatCodeType::Sales, $created->type);
            self::assertSame('SDK test', $created->shortName);
            self::assertCount(1, $created->percentages);
            self::assertSame('21', $created->percentages[0]->percentage->value);
            self::assertSame('SDK test 21%', $created->percentages[0]->name);
            self::assertFalse($created->percentages[0]->inUse);
            self::assertCount(count($accounts), $created->percentages[0]->accounts);

            try {
                $vatCodes->create(VatCodeInput::sales($code, 'Duplicate'));
                self::fail('Expected a VatCodeAlreadyExistsException.');
            } catch (VatCodeAlreadyExistsException) {
                self::assertSame('SDK test VAT code', $vatCodes->get($code)->name, 'The existing VAT code is unchanged.');
            }

            $unchanged = $vatCodes->update($created->edit());

            self::assertEquals($created->percentages, $unchanged->percentages, 'An update with edit() changes nothing.');

            $updated = $vatCodes->update(
                $created->edit()
                    ->name('SDK test VAT code (updated)')
                    ->shortName(null)
                    ->newPercentageFrom(new DateTimeImmutable('2026-01-01'), '22', 'SDK test 22%'),
            );

            self::assertSame('SDK test VAT code (updated)', $updated->name);
            self::assertNull($updated->shortName, 'An update clears values that were removed.');
            self::assertCount(2, $updated->percentages, 'The existing percentage is kept, not added again.');
            self::assertSame('21', $updated->percentageOn(new DateTimeImmutable('2025-12-31'))?->percentage->value);
            self::assertSame('22', $updated->percentageOn(new DateTimeImmutable('2026-01-01'))?->percentage->value);
            self::assertSame('22.00', $updated->vatOn('100.00', new DateTimeImmutable('2026-03-01'))->value);

            self::assertSame(DeleteResult::Deleted, $vatCodes->delete($code));
            self::assertSame(VatCodeStatus::Deleted, $vatCodes->get($code)->status);

            $vatCodes->activate($code);
            self::assertSame(VatCodeStatus::Active, $vatCodes->get($code)->status);
        } finally {
            if ($vatCodes->find($code)?->status === VatCodeStatus::Active) {
                $vatCodes->delete($code);
            }
        }
    }

    /**
     * @return non-empty-list<VatAccount>
     */
    private function salesVatCodeAccounts(): array
    {
        $codes = array_map(
            static fn ($summary): string => $summary->code,
            $this->twinfield->vatCodes()->search()->vatType(VatCodeType::Sales)->get()->items,
        );
        $code = in_array('VH', $codes, true) ? 'VH' : $codes[0] ?? self::fail('The company has no sales VAT code.');

        return $this->twinfield->vatCodes()->get($code)->latestPercentage()->accounts;
    }

    private function unusedCode(): string
    {
        for ($number = 999; $number > 900; $number--) {
            $code = sprintf('SDKTEST%03d', $number);

            if ($this->twinfield->vatCodes()->find($code) === null) {
                return $code;
            }
        }

        self::fail('No unused VAT code SDKTEST901–SDKTEST999.');
    }
}
