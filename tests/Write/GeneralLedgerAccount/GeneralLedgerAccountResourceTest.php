<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\GeneralLedgerAccount;

use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\GeneralLedgerAccount\ChildValidation;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountInput;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountResource;
use SolveBeam\Twinfield\GeneralLedgerAccount\SubAnalysis;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\VatCode\VatCodeSummary;
use SolveBeam\Twinfield\VatCode\VatCodeType;

/**
 * Creates a temporary balance sheet and profit and loss account with a free code, changes,
 * deletes and reactivates them; always deletes them again.
 *
 * This also confirms what the SDK assumes about writes: `accounttype` does not have to be sent,
 * and an empty `<childvalidations/>` removes the child validations.
 */
final class GeneralLedgerAccountResourceTest extends WriteTestCase
{
    public function testProfitAndLossLifecycle(): void
    {
        $accounts = $this->twinfield->profitAndLossAccounts();
        $code = Example::unusedGeneralLedgerAccountCode($accounts);
        $vatCode = $this->salesVatCode();

        $this->lifecycle(
            $accounts,
            GeneralLedgerAccountInput::profitAndLoss($code, 'SDK test revenue')
                ->shortName('SDK test')
                ->vatCode($vatCode)
                ->subAnalysis(SubAnalysis::Required)
                ->childValidation(ChildValidation::dimensionType('KPL')),
        );

        $read = $accounts->get($code);

        self::assertSame($vatCode, $read->vatCode?->code);
    }

    public function testBalanceSheetLifecycle(): void
    {
        $accounts = $this->twinfield->balanceSheetAccounts();

        $this->lifecycle(
            $accounts,
            GeneralLedgerAccountInput::balanceSheet(Example::unusedGeneralLedgerAccountCode($accounts), 'SDK test suspense')
                ->matchable()
                ->subAnalysis(SubAnalysis::Optional)
                ->childValidation(ChildValidation::dimensionType('DEB'))
                ->childValidation(ChildValidation::dimensionType('CRD')),
        );
    }

    public function testRefusesAnInputOfTheOtherType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield->balanceSheetAccounts()->create(GeneralLedgerAccountInput::profitAndLoss('8999', 'Wrong type'));
    }

    private function lifecycle(GeneralLedgerAccountResource $accounts, GeneralLedgerAccountInput $input): void
    {
        $code = $input->code;
        $created = false;

        try {
            $account = $accounts->create($input);
            $created = true;

            self::assertSame($accounts->type(), $account->type);
            self::assertSame(DimensionStatus::Active, $account->status);
            self::assertFalse($account->inUse);

            $read = $accounts->get($code);

            self::assertSame($input->name, $read->name);
            self::assertSame($account->uid, $read->uid);
            self::assertSame($input->value('financials/subanalyse'), $read->subAnalysis->value);
            self::assertEquals($input->childValidations, $read->childValidations);

            try {
                $accounts->create(GeneralLedgerAccountInput::of($accounts->type(), $code, 'Duplicate'));
                self::fail('Expected a DimensionAlreadyExistsException.');
            } catch (DimensionAlreadyExistsException) {
                self::assertSame($input->name, $accounts->get($code)->name, 'The existing account is unchanged.');
            }

            // An empty <childvalidations/> removes them.
            $updated = $accounts->update(
                $read->edit()->name($input->name . ' (updated) & more')->subAnalysis(SubAnalysis::NotAllowed)->childValidations(),
            );

            self::assertSame($input->name . ' (updated) & more', $updated->name);

            $read = $accounts->get($code);

            self::assertSame(SubAnalysis::NotAllowed, $read->subAnalysis);
            self::assertSame([], $read->childValidations);

            self::assertSame(DeleteResult::Deleted, $accounts->delete($code));
            self::assertSame(DimensionStatus::Deleted, $accounts->get($code)->status);

            $accounts->activate($code);
            self::assertSame(DimensionStatus::Active, $accounts->get($code)->status);
        } finally {
            if ($created && $accounts->find($code)?->status === DimensionStatus::Active) {
                $accounts->delete($code);
            }
        }
    }

    private function salesVatCode(): string
    {
        $codes = array_map(
            static fn (VatCodeSummary $summary): string => $summary->code,
            $this->twinfield->vatCodes()->search()->vatType(VatCodeType::Sales)->get()->items,
        );

        // VH (high rate) in Dutch companies, or else any sales VAT code.
        return in_array('VH', $codes, true) ? 'VH' : $codes[0] ?? self::fail('The company has no sales VAT code.');
    }
}
