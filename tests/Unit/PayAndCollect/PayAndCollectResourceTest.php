<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\PayAndCollect;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\PayAndCollect\CollectionSchema;
use SolveBeam\Twinfield\PayAndCollect\PayAndCollectException;
use SolveBeam\Twinfield\PayAndCollect\PayAndCollectMessageType;
use SolveBeam\Twinfield\PayAndCollect\PayRun;
use SolveBeam\Twinfield\PayAndCollect\PayRunInput;
use SolveBeam\Twinfield\PayAndCollect\PayRunMethod;
use SolveBeam\Twinfield\PayAndCollect\PayRunModification;
use SolveBeam\Twinfield\PayAndCollect\PayRunPhase;
use SolveBeam\Twinfield\PayAndCollect\PayRunStatus;
use SolveBeam\Twinfield\PayAndCollect\PayRunType;
use SolveBeam\Twinfield\PayAndCollect\PayRunVersion;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class PayAndCollectResourceTest extends TestCase
{
    public function testListsNoRuns(): void
    {
        $http = ReplayClient::fixture('pay-and-collect-get-runs-empty');

        self::assertSame([], $this->twinfield($http)->payAndCollect()->list());
        self::assertSame('http://www.twinfield.com/GetRuns', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringEndsWith('/webservices/payandcollect.asmx', (string) $http->lastRequest()->getUri());
        self::assertStringContainsString('<GetRuns><office>1000</office></GetRuns>', $this->body($http));
        self::assertStringContainsString('<CompanyCode>1000</CompanyCode>', $this->body($http));
    }

    public function testListsRuns(): void
    {
        $runs = $this->twinfield(ReplayClient::fixture('pay-and-collect-get-runs'))->payAndCollect()->list();

        self::assertCount(2, $runs);

        [$payment, $directDebit] = $runs;

        self::assertSame('1000', $payment->company->code);
        self::assertSame('SEPANLCT', $payment->payType);
        self::assertSame(12, $payment->id);
        self::assertEquals(new DateTimeImmutable('2026-11-10 09:15:00'), $payment->created);
        self::assertSame(2, $payment->version->touched);
        self::assertSame('2026-11-10T09:16:30.157', $payment->version->modifiedValue);
        self::assertSame('2026-11-10 09:16:30.157', $payment->version->modified->format('Y-m-d H:i:s.v'));
        self::assertSame('JDOE', $payment->user);
        self::assertSame(PayRunType::Payment, $payment->runType);
        self::assertSame(PayRunPhase::Select, $payment->phase);
        self::assertSame(PayRunStatus::Done, $payment->status);
        self::assertSame('ING', $payment->bank);
        self::assertEquals(new DateTimeImmutable('2026-11-12'), $payment->payDate);
        self::assertSame('2026/11', (string) $payment->period);
        self::assertFalse($payment->directRemove);
        self::assertSame(['PayCostNational' => '0'], $payment->customSettings);

        self::assertSame(PayRunType::DirectDebit, $directDebit->runType);
        self::assertSame(PayRunPhase::Authorise, $directDebit->phase);
        self::assertSame(PayRunStatus::DoneWithErrors, $directDebit->status);
        self::assertTrue($directDebit->directRemove);
        self::assertSame([], $directDebit->customSettings);
    }

    public function testGetsARunWithSummaries(): void
    {
        $http = ReplayClient::fixture('pay-and-collect-get-run-info');
        $run = $this->twinfield($http)->payAndCollect()->get('SEPANLCT', 12);

        self::assertSame('http://www.twinfield.com/GetRunInfo', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<GetRunInfo><office>1000</office><payType>SEPANLCT</payType><runId>12</runId></GetRunInfo>',
            $this->body($http),
        );

        self::assertSame(12, $run->id);
        self::assertCount(2, $run->summaries);

        $summary = $run->summary(1);

        self::assertNotNull($summary);
        self::assertSame('2001', $summary->relation);
        self::assertSame('Example Supplier B.V.', $summary->relationName);
        self::assertSame('NL02ABNA0123456789', $summary->relationBank?->iban);
        self::assertSame('ABNANL2A', $summary->relationBank->internationalCode);
        self::assertNull($summary->relationBank->accountNumber);
        self::assertSame('EUR', $summary->currency);
        self::assertSame('Euro', $summary->currencyName);
        self::assertSame('Invoices 123, 124', $summary->reference);
        self::assertTrue($summary->included);
        self::assertSame('6171.00', (string) $summary->value());

        $detail = $summary->detail(2);

        self::assertNotNull($detail);
        self::assertSame('INK', $detail->transaction);
        self::assertSame('Inkoopfactuur', $detail->transactionName);
        self::assertSame(202600013, $detail->transactionNumber);
        self::assertSame(1, $detail->transactionLine);
        self::assertSame('124', $detail->invoiceNumber);
        self::assertEquals(new DateTimeImmutable('2026-10-15'), $detail->invoiceDate);
        self::assertEquals(new DateTimeImmutable('2026-11-14'), $detail->invoiceDueDate);
        self::assertSame('121.00', (string) $detail->value);
        self::assertSame('121.00', (string) $detail->originalBaseValue);
        self::assertSame('2.42', (string) $detail->discountAmount);
        self::assertTrue($detail->included);

        self::assertNull($run->summary(2)?->relationBank);
        self::assertNull($run->summary(3));
    }

    public function testCreatesARun(): void
    {
        $http = ReplayClient::fixture('pay-and-collect-create-run');
        $created = $this->twinfield($http)->payAndCollect()->create(
            PayRunInput::create('SEPANLCT', new DateTimeImmutable('2026-11-12 15:30'), '2026/11')
                ->method(PayRunMethod::SummariseAndDeduct)
                ->directRemove(false)
                ->bank('ING')
                ->dimensions('2000', '2999')
                ->maxDueDate(new DateTimeImmutable('2026-11-30'))
                ->invoiceNumber('123')
                ->currency('EUR')
                ->discountDaysTolerance(3)
                ->setting('PayCostNational', '0'),
        );

        self::assertSame('http://www.twinfield.com/CreateRun2', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<CreateRun2><createRunRequest><Office>1000</Office><PayType>SEPANLCT</PayType>'
            . '<PayDate>2026-11-12T00:00:00</PayDate><Period>2026/11</Period><Method>SummariseAndDeduct</Method>'
            . '<DirectRemove>false</DirectRemove><BankCode>ING</BankCode><DimensionFrom>2000</DimensionFrom>'
            . '<DimensionTo>2999</DimensionTo><MaxDuedate>2026-11-30T00:00:00</MaxDuedate>'
            . '<InvoiceNumber>123</InvoiceNumber><Currency>EUR</Currency>'
            . '<DiscountDaysTolerance>3</DiscountDaysTolerance>'
            . '<CustomSettings><ArrayOfString><string>PayCostNational</string><string>0</string></ArrayOfString>'
            . '</CustomSettings></createRunRequest></CreateRun2>',
            $this->body($http),
        );

        self::assertSame(12, $created->run->id);
        self::assertCount(2, $created->run->summaries);
        self::assertCount(1, $created->messages);
        self::assertSame(PayAndCollectMessageType::Informational, $created->messages[0]->type);
        self::assertSame('3 transacties geselecteerd, 1 overgeslagen.', $created->messages[0]->text);
    }

    public function testCreatesARunWithOnlyTheRequiredFields(): void
    {
        $http = ReplayClient::fixture('pay-and-collect-create-run');

        $this->twinfield($http)->payAndCollect()->create(
            PayRunInput::create('SEPANLDD', new DateTimeImmutable('2026-11-12'), '2026/11')
                ->collectionSchema(CollectionSchema::B2B),
        );

        self::assertStringContainsString(
            '<createRunRequest><Office>1000</Office><PayType>SEPANLDD</PayType><PayDate>2026-11-12T00:00:00</PayDate>'
            . '<Period>2026/11</Period><DiscountDaysTolerance>0</DiscountDaysTolerance><CustomSettings><ArrayOfString>'
            . '<string>SEPANLDDcollectionschema</string><string>1</string></ArrayOfString></CustomSettings>'
            . '</createRunRequest>',
            $this->body($http),
        );
    }

    public function testThrowsWhenCreatingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('pay-and-collect-create-run-error'))
                ->payAndCollect()
                ->create(PayRunInput::create('SEPANLCT', new DateTimeImmutable('2026-11-12'), '2026/11'));
            self::fail('Expected PayAndCollectException.');
        } catch (PayAndCollectException $exception) {
            self::assertSame('CreateRun2', $exception->operation);
            self::assertSame('CreateRun2: Er zijn geen transacties geselecteerd.', $exception->getMessage());
            self::assertSame('UnknownTextualMessage', $exception->messages[0]->code);
            self::assertSame([], $exception->messages[0]->parameters);
        }
    }

    public function testStartsCreatingARunAsynchronously(): void
    {
        $http = ReplayClient::fixture('pay-and-collect-create-run-async');

        $messages = $this->twinfield($http)->payAndCollect()->createAsync(
            PayRunInput::create('SEPANLCT', new DateTimeImmutable('2026-11-12'), '2026/11'),
        );

        self::assertSame([], $messages);
        self::assertSame('http://www.twinfield.com/CreateRunAsync', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<CreateRunAsync><createRunAsyncRequest><Office>1000</Office>',
            $this->body($http),
        );
    }

    public function testModifiesARun(): void
    {
        $run = $this->payRun();
        $http = ReplayClient::fixture('pay-and-collect-modify');

        $version = $this->twinfield($http)->payAndCollect()->modify(
            PayRunModification::of($run)
                ->excludeSummary(2)
                ->value(1, 1, '6000')
                ->discount(1, 1, '10.50')
                ->excludeDetail(1, 2),
        );

        self::assertSame('http://www.twinfield.com/Modify', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<Modify><office>1000</office><payType>SEPANLCT</payType><runId>12</runId><touched>2</touched>'
            . '<modified>2026-11-10T09:16:30.157</modified><newSummaries>'
            . '<SummarySettings><Relation>2001</Relation><Currency>EUR</Currency><Id>1</Id>'
            . '<Included>true</Included><Details>'
            . '<DetailSettings><Id>1</Id><Value>6000.00</Value><Included>true</Included>'
            . '<DiscountAmount>10.50</DiscountAmount></DetailSettings>'
            . '<DetailSettings><Id>2</Id><Value>121.00</Value><Included>false</Included>'
            . '<DiscountAmount>2.42</DiscountAmount></DetailSettings>'
            . '</Details></SummarySettings>'
            . '<SummarySettings><Relation>2005</Relation><Currency>EUR</Currency><Id>2</Id>'
            . '<Included>false</Included><Details>'
            . '<DetailSettings><Id>1</Id><Value>111.74</Value><Included>true</Included>'
            . '<DiscountAmount>0.00</DiscountAmount></DetailSettings>'
            . '</Details></SummarySettings></newSummaries></Modify>',
            $this->body($http),
        );

        self::assertSame(3, $version->touched);
        self::assertSame('2026-11-10T09:20:00.5', $version->modifiedValue);
        self::assertSame([], $version->messages);
    }

    public function testRefusesAnInvalidModification(): void
    {
        $modification = PayRunModification::of($this->payRun());

        $cases = [
            'unknown summary' => static fn () => $modification->excludeSummary(3),
            'unknown detail' => static fn () => $modification->excludeDetail(1, 3),
            'zero value' => static fn () => $modification->value(1, 1, '0'),
            'more than original' => static fn () => $modification->value(1, 2, '121.01'),
            'negative discount' => static fn () => $modification->discount(1, 2, '-1'),
        ];

        foreach ($cases as $case => $modify) {
            try {
                $modify();
                self::fail('Expected an InvalidArgumentException: ' . $case);
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAuthorisesWithTheVersionOfTheModification(): void
    {
        $run = $this->payRun();
        $http = ReplayClient::fixture('pay-and-collect-authorize');
        $payAndCollect = $this->twinfield($http)->payAndCollect();

        $version = $payAndCollect->authorise($run, new PayRunVersion(
            3,
            new DateTimeImmutable('2026-11-10 09:20:00.5'),
            '2026-11-10T09:20:00.5',
        ));

        self::assertSame('http://www.twinfield.com/Authorize', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<Authorize><office>1000</office><payType>SEPANLCT</payType><runId>12</runId><touched>3</touched>'
            . '<modified>2026-11-10T09:20:00.5</modified></Authorize>',
            $this->body($http),
        );
        self::assertSame(3, $version->touched);
    }

    public function testThrowsWhenAuthorisingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('pay-and-collect-authorize-error'))
                ->payAndCollect()
                ->authorise($this->payRun());
            self::fail('Expected PayAndCollectException.');
        } catch (PayAndCollectException $exception) {
            self::assertSame('Authorize', $exception->operation);
            self::assertCount(2, $exception->messages);
            self::assertSame(
                'Authorize: De betaalrun is gewijzigd door een andere gebruiker. (and 1 more errors)',
                $exception->getMessage(),
            );
        }
    }

    public function testPostsAndReturnsTheWarnings(): void
    {
        $http = ReplayClient::fixture('pay-and-collect-post');

        $version = $this->twinfield($http)->payAndCollect()->post($this->payRun());

        self::assertSame('http://www.twinfield.com/Post', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<Post><office>1000</office><payType>SEPANLCT</payType><runId>12</runId><touched>2</touched>',
            $this->body($http),
        );
        self::assertSame(3, $version->touched);
        self::assertCount(1, $version->messages);
        self::assertSame(PayAndCollectMessageType::Warning, $version->messages[0]->type);
    }

    public function testStartsPostingAsynchronously(): void
    {
        $http = ReplayClient::fixture('pay-and-collect-post-async');

        $version = $this->twinfield($http)->payAndCollect()->postAsync($this->payRun());

        self::assertSame('http://www.twinfield.com/PostAsync', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertSame(3, $version->touched);
    }

    public function testCreatesFiles(): void
    {
        $http = ReplayClient::fixture('pay-and-collect-create-files');

        $files = $this->twinfield($http)->payAndCollect()->createFiles(
            $this->payRun(),
            exportSettings: ['FileType' => 'production'],
        );

        self::assertSame('http://www.twinfield.com/CreateFiles', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<modified>2026-11-10T09:16:30.157</modified><exportSettings><ArrayOfString><string>FileType</string>'
            . '<string>production</string></ArrayOfString></exportSettings></CreateFiles>',
            $this->body($http),
        );
        self::assertSame(
            ['<?xml version="1.0"?><Document>1</Document>', '<?xml version="1.0"?><Document>2</Document>'],
            $files->files,
        );
        self::assertSame(3, $files->version->touched);
    }

    public function testFinishes(): void
    {
        $http = ReplayClient::fixture('pay-and-collect-finish');

        self::assertSame([], $this->twinfield($http)->payAndCollect()->finish($this->payRun()));
        self::assertSame('http://www.twinfield.com/Finish', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<Finish><office>1000</office><payRunCode>SEPANLCT</payRunCode><runId>12</runId><touched>2</touched>'
            . '<modified>2026-11-10T09:16:30.157</modified></Finish>',
            $this->body($http),
        );
    }

    public function testThrowsOnAnUnexpectedResponse(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected <FinishResponse>, got <AuthorizeResponse>.');

        $this->twinfield(ReplayClient::fixture('pay-and-collect-authorize'))->payAndCollect()->finish($this->payRun());
    }

    public function testThrowsOnARunOfAnotherCompany(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'GetRunsResponse/GetRunsResult/PayOverview[1]/Office is "1000", '
            . 'expected the company of the request "2000".',
        );

        $psr17 = new Psr17Factory();
        Twinfield::create(
            TestTokens::provider('https://example.test'),
            ReplayClient::fixture('pay-and-collect-get-runs'),
            $psr17,
            $psr17,
        )->company('2000')->payAndCollect()->list();
    }

    public function testRefusesAnInvalidRunBeforeSending(): void
    {
        $http = new ReplayClient();

        foreach ([['', 1], ['SEPANLCT', 0]] as [$payType, $id]) {
            try {
                $this->twinfield($http)->payAndCollect()->get($payType, $id);
                self::fail('Expected an InvalidArgumentException.');
            } catch (InvalidArgumentException) {
                self::assertSame([], $http->requests);
            }
        }
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(
            TestTokens::provider('https://example.test'),
            new ReplayClient(),
            $psr17,
            $psr17,
        );

        $this->expectException(LogicException::class);

        $twinfield->payAndCollect()->list();
    }

    private function payRun(): PayRun
    {
        return $this->twinfield(ReplayClient::fixture('pay-and-collect-get-run-info'))
            ->payAndCollect()
            ->get('SEPANLCT', 12);
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }

    private function body(ReplayClient $http): string
    {
        $body = (string) preg_replace('~>\s+<~', '><', (string) $http->lastRequest()->getBody());

        return (string) preg_replace('~ xmlns(:\w+)?="[^"]*"~', '', $body);
    }
}
