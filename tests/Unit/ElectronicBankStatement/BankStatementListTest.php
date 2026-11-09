<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ElectronicBankStatement;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\ElectronicBankStatement\BankStatementAllocationMethodType;
use SolveBeam\Twinfield\ElectronicBankStatement\BankStatementAllocationRecognition;
use SolveBeam\Twinfield\ElectronicBankStatement\BankStatementAllocationRuleStorageLevel;
use SolveBeam\Twinfield\ElectronicBankStatement\BankStatementAllocationType;
use SolveBeam\Twinfield\ElectronicBankStatement\BankStatementAmountOperator;
use SolveBeam\Twinfield\ElectronicBankStatement\BankStatementMatching;
use SolveBeam\Twinfield\ElectronicBankStatement\BankStatementVatLine;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Reading bank statements (BankStatementService). `electronic-bank-statements-list` is a recorded,
 * anonymized response (one day, 14 lines); `-unposted` and `-invalid-amount` are fictitious, for
 * what the recording does not have: a statement that is not posted, VAT lines and an amount rule.
 */
final class BankStatementListTest extends TestCase
{
    public function testListsStatementsWithLinesAndAllocations(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statements-list');

        $statements = $this->twinfield($http)->electronicBankStatements()->list(
            from: new DateTimeImmutable('2026-11-05T15:00:00'),
            to: new DateTimeImmutable('2026-11-05T15:00:00'),
        );

        $request = $http->lastRequest();

        self::assertSame('https://example.test/webservices/BankStatementService.svc', (string) $request->getUri());
        self::assertSame('"http://www.twinfield.com/BankStatementService/Query"', $request->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            'i:type="a:GetBankStatements">'
            . '<a:IncludePostedStatements>true</a:IncludePostedStatements>'
            . '<a:StatementDateFrom>2026-11-05T00:00:00</a:StatementDateFrom>'
            . '<a:StatementDateTo>2026-11-05T00:00:00</a:StatementDateTo>'
            . '</Query>',
            $this->body($http),
            'Only the date is sent: statements are dated at midnight and Twinfield compares the time too.',
        );

        self::assertCount(1, $statements);

        $statement = $statements[0];

        self::assertTrue($statement->company->equals(TestTokens::company('1000')));
        self::assertSame('GWPO', $statement->bankBook);
        self::assertSame(4914, $statement->number);
        self::assertSame(1, $statement->subId);
        self::assertSame('127160099', $statement->accountNumber);
        self::assertSame('TS04ODEF6943262301', $statement->iban);
        self::assertSame('2025-10-04 00:00:00', $statement->date->format('Y-m-d H:i:s'));
        self::assertSame('EUR', $statement->currency);
        self::assertSame('32179.30', $statement->openingBalance->value);
        self::assertSame('12278.60', $statement->closingBalance->value);
        self::assertSame(838025747, $statement->transactionNumber);
        self::assertTrue($statement->isPosted());
        self::assertCount(14, $statement->lines);
        self::assertSame(range(1, 14), array_map(static fn ($line): int => $line->lineId, $statement->lines));

        // Paid to a supplier, recognised by IBAN and matched with the purchase invoice.
        $paid = $statement->lines[0];

        self::assertSame('-25.18', $paid->amount->value);
        self::assertSame('-25.18', $paid->baseAmount->value);
        self::assertNull($paid->contraAccountNumber, 'Empty in the response.');
        self::assertSame('CE15WDEA0005422626', $paid->contraIban);
        self::assertSame('Ioykcnakp Shujp Z.D.', $paid->contraAccountName);
        self::assertNull($paid->paymentReference);
        self::assertSame('QNB 19732535', $paid->description);
        self::assertSame('S279', $paid->transactionTypeId);
        self::assertSame('5312341SU613356', $paid->reference);
        self::assertSame('5312341SU613356', $paid->endToEndId);
        self::assertNull($paid->returnReason);
        self::assertSame(BankStatementAllocationRecognition::ContraIban, $paid->recognition);
        self::assertSame([], $paid->allocationMethods);
        self::assertSame(BankStatementAllocationRuleStorageLevel::NotStored, $paid->allocationRuleStorageLevel);
        self::assertCount(1, $paid->allocations);

        $allocation = $paid->allocations[0];

        self::assertSame(BankStatementAllocationType::Supplier, $allocation->type);
        self::assertSame(DebitCredit::Debit, $allocation->debitCredit);
        self::assertSame('713.84', $allocation->amount->value);
        self::assertSame('713.84', $allocation->baseAmount->value);
        self::assertSame([1 => '63821', 2 => '7039'], $allocation->dimensions);
        self::assertSame('7039', $allocation->dimension(2));
        self::assertNull($allocation->dimension(3));
        self::assertNull($allocation->descriptionFormat);
        self::assertFalse($allocation->saveDescriptionFormatToAllocationRule);
        self::assertNull($allocation->chequeNumber);
        self::assertNull($allocation->payingInSlipNumber);
        self::assertSame([], $allocation->vatLines);
        self::assertEquals(
            [new BankStatementMatching('RLU', 823698474, 1, Amount::of('-25.18'))],
            $allocation->matchings,
        );

        // Received, allocated by a rule on IBAN and description that is saved for the company.
        $received = $statement->lines[1];

        self::assertSame('2628.43', $received->amount->value);
        self::assertNull($received->endToEndId);
        self::assertSame(BankStatementAllocationRecognition::AllocationRule, $received->recognition);
        self::assertSame(BankStatementAllocationRuleStorageLevel::LocalOffice, $received->allocationRuleStorageLevel);
        self::assertCount(2, $received->allocationMethods);
        self::assertSame(BankStatementAllocationMethodType::ContraIban, $received->allocationMethods[0]->type);
        self::assertSame('HS41BRSA7621850265', $received->allocationMethods[0]->text);
        self::assertNull($received->allocationMethods[0]->amountOperator);
        self::assertNull($received->allocationMethods[0]->amount);
        self::assertSame(BankStatementAllocationMethodType::Description, $received->allocationMethods[1]->type);
        self::assertSame(DebitCredit::Credit, $received->allocations[0]->debitCredit);
        self::assertSame('[bpjnyggz_fjeonpkhnhs]', $received->allocations[0]->descriptionFormat);

        // Split over two accounts, not recognised. Live the signed allocations add up to the line;
        // anonymizing scrambles each amount on its own, so that is checked in the unposted test.
        $split = $statement->lines[4];

        self::assertNull($split->recognition);
        self::assertCount(2, $split->allocations);
        self::assertSame('3.74', $split->allocations[0]->signedAmount()->value);
        self::assertSame('-6.31', $split->allocations[1]->signedAmount()->value);
    }

    public function testListsAStatementThatIsNotPosted(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statements-list-unposted');

        $statements = $this->twinfield($http)->electronicBankStatements()->list(
            from: new DateTimeImmutable('2026-11-01'),
            to: new DateTimeImmutable('2026-11-30'),
            includePosted: false,
        );

        self::assertStringContainsString(
            '<a:IncludePostedStatements>false</a:IncludePostedStatements>',
            $this->body($http),
        );

        $statement = $statements[0];

        self::assertNull($statement->transactionNumber);
        self::assertFalse($statement->isPosted());
        self::assertNull($statement->accountNumber);
        self::assertSame(2, $statement->subId);
        self::assertSame('-121.01', $statement->total()->value);
        self::assertSame(
            $statement->closingBalance->value,
            $statement->openingBalance->plus($statement->total())->value,
        );

        $line = $statement->lines[0];

        self::assertSame('1234567890', $line->contraAccountNumber);
        self::assertNull($line->contraIban, 'Nil in the response.');
        self::assertNull($line->contraAccountName);
        self::assertNull($line->transactionTypeId);
        self::assertSame('RF18539007547034', $line->paymentReference);
        self::assertSame('AC04', $line->returnReason);
        self::assertSame(BankStatementAllocationRuleStorageLevel::LocalBank, $line->allocationRuleStorageLevel);

        $method = $line->allocationMethods[0];

        self::assertSame(BankStatementAllocationMethodType::Amount, $method->type);
        self::assertNull($method->text);
        self::assertSame(BankStatementAmountOperator::MoreThan, $method->amountOperator);
        self::assertSame('100.00', $method->amount?->value);

        $allocation = $line->allocations[0];

        self::assertSame('CHQ-0042', $allocation->chequeNumber);
        self::assertSame(12345, $allocation->payingInSlipNumber);
        self::assertTrue($allocation->saveDescriptionFormatToAllocationRule);
        self::assertEquals([new BankStatementVatLine('VH', Amount::of('100'), Amount::of('21'), false)], $allocation->vatLines);
        self::assertSame($line->amount->value, $allocation->signedAmount()->value, 'Money paid is booked debit.');
    }

    public function testListsNothing(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statements-list-empty');

        self::assertSame([], $this->twinfield($http)->electronicBankStatements()->list(
            new DateTimeImmutable('2027-06-01'),
            new DateTimeImmutable('2027-06-30'),
        ));
    }

    public function testListsByCreationDateWithTheTimeAsGiven(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statements-list-empty');

        $this->twinfield($http)->electronicBankStatements()->listByCreationDate(
            from: new DateTimeImmutable('2026-11-08T12:30:00'),
        );

        self::assertStringContainsString(
            'i:type="a:GetBankStatementsByCreationDate">'
            . '<a:CreationDateFrom>2026-11-08T12:30:00</a:CreationDateFrom>'
            . '<a:IncludePostedStatements>true</a:IncludePostedStatements>'
            . '</Query>',
            $this->body($http),
        );
    }

    public function testListsByCreationDateUpTo(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statements-list-empty');

        $this->twinfield($http)->electronicBankStatements()->listByCreationDate(
            from: new DateTimeImmutable('2026-11-08T00:00:00'),
            to: new DateTimeImmutable('2026-11-08T23:59:59'),
            includePosted: false,
        );

        self::assertStringContainsString(
            '<a:CreationDateFrom>2026-11-08T00:00:00</a:CreationDateFrom>'
            . '<a:CreationDateTo>2026-11-08T23:59:59</a:CreationDateTo>'
            . '<a:IncludePostedStatements>false</a:IncludePostedStatements>',
            $this->body($http),
        );
    }

    public function testRejectsFromAfterTo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('From date 2026-11-05 is after to date 2026-11-04.');

        $this->twinfield(new ReplayClient())->electronicBankStatements()->list(
            new DateTimeImmutable('2026-11-05'),
            new DateTimeImmutable('2026-11-04T23:59:59'),
        );
    }

    public function testAcceptsTheSameDayWithAnEarlierTime(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statements-list-empty');

        $this->twinfield($http)->electronicBankStatements()->list(
            new DateTimeImmutable('2026-11-04T23:00:00'),
            new DateTimeImmutable('2026-11-04T08:00:00'),
        );

        self::assertStringContainsString('<a:StatementDateTo>2026-11-04T00:00:00</a:StatementDateTo>', $this->body($http));
    }

    public function testRejectsCreationFromAfterTo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('From date 2026-11-08 12:00:00 is after to date 2026-11-08 08:00:00.');

        $this->twinfield(new ReplayClient())->electronicBankStatements()->listByCreationDate(
            new DateTimeImmutable('2026-11-08T12:00:00'),
            new DateTimeImmutable('2026-11-08T08:00:00'),
        );
    }

    public function testRejectsCreationDateWithoutDates(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield(new ReplayClient())->electronicBankStatements()->listByCreationDate();
    }

    public function testThrowsOnAnAmountWithMoreThanTwoDecimals(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statements-list-invalid-amount');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'GetBankStatementsResult/BankStatements/BankStatement[1]/Lines/BankStatementLine[1]/Amount'
            . ' is not an amount with at most 2 decimals: "-121.0150".',
        );

        $this->twinfield($http)->electronicBankStatements()->list(
            new DateTimeImmutable('2026-11-01'),
            new DateTimeImmutable('2026-11-30'),
        );
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);

        $twinfield->electronicBankStatements()->list(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-30'));
    }

    private function body(ReplayClient $http): string
    {
        return (string) preg_replace('~>\s+<~', '><', (string) $http->lastRequest()->getBody());
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }
}
