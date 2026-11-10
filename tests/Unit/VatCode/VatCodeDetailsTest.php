<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\VatCode;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\VatCode\VatCodeDetails;
use SolveBeam\Twinfield\VatCode\VatCodeType;

final class VatCodeDetailsTest extends TestCase
{
    public function testGetsAVatCodeById(): void
    {
        $http = ReplayClient::jsonFixture('vat-codes-get');

        $vatCode = TestTokens::restClient($http)->vatCodes()->getDetails('b88886c7-de77-51c8-021c-d60e2ff53953');

        self::assertSame('KST_QST', $vatCode->code);
        self::assertSame(VatCodeType::Sales, $vatCode->type);
        self::assertSame('Powwgh Jctufg PF', $vatCode->name);
        self::assertNull($vatCode->invoiceText);
        self::assertSame([], $vatCode->translations);
        self::assertCount(1, $vatCode->rates);

        $rate = $vatCode->rates[0];
        $rule = $rate->allocationRules[0];

        self::assertSame('0', (string) $rate->percentage);
        self::assertSame('2008-11-30', $rate->startDate->format('Y-m-d'));
        self::assertNull($rate->discountAccountId);
        self::assertTrue($rule->isVatLine);
        self::assertSame('100', (string) $rule->percentage);
        self::assertSame('d68597e8-0cba-c83a-ae07-cbb60c6e3ec7', $rule->generalLedgerAccountId);
        self::assertNull($rule->costCenterId);
        self::assertSame('f8a32f9e-8cea-4aa0-a215-15a0971f807a', $rule->vatGroupId);
        self::assertSame(
            TestTokens::REST_COMPANY_URL . '/vat/vatcodes/b88886c7-de77-51c8-021c-d60e2ff53953',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testSearchesVatCodesAndFindsTheRateOnADate(): void
    {
        $http = ReplayClient::jsonFixture('vat-codes-list');

        $page = TestTokens::restClient($http)->vatCodes()->searchDetails('D*')->type(VatCodeType::Sales)->orderByCode()->get();

        $vatCodes = array_filter($page->items, static fn (VatCodeDetails $vatCode): bool => $vatCode->code === 'DOB-L');
        $vatCode = array_values($vatCodes)[0];

        self::assertSame('19', (string) $vatCode->rateOn(new DateTimeImmutable('2010-01-01'))?->percentage);
        self::assertSame('21', (string) $vatCode->rateOn(new DateTimeImmutable('2026-01-01'))?->percentage);
        self::assertNull($vatCode->rateOn(new DateTimeImmutable('2000-01-01')));
        self::assertSame(
            TestTokens::REST_COMPANY_URL . '/vat/vatcodes?searchPattern=D%2A&type=sales&orderBy=code&asc=true',
            (string) $http->lastRequest()->getUri(),
        );
    }
}
