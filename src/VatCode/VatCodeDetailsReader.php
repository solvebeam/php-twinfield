<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;

/**
 * Maps the JSON of the REST API onto VatCodeDetails.
 *
 * @internal
 */
final class VatCodeDetailsReader
{
    public static function read(JsonValue $json, CompanyReference $company): VatCodeDetails
    {
        return new VatCodeDetails(
            company: $company,
            id: $json->uuid('id'),
            code: $json->string('code'),
            type: $json->enum('type', VatCodeType::class),
            name: $json->string('name'),
            shortName: $json->string('shortName'),
            invoiceText: $json->nullableString('invoiceText'),
            translations: self::translations($json),
            rates: array_map(self::rate(...), $json->list('rates')),
            version: $json->int('version'),
            createdAt: $json->timestamp('createdAt'),
            lastModifiedAt: $json->timestamp('lastModifiedAt'),
            modifiedBy: $json->string('modifiedBy'),
        );
    }

    private static function rate(JsonValue $rate): VatRate
    {
        return new VatRate(
            id: $rate->uuid('id'),
            name: $rate->string('name'),
            shortName: $rate->string('shortName'),
            startDate: $rate->date('startDate'),
            percentage: Percentage::of($rate->decimal('percent')),
            discountAccountId: $rate->nullableUuid('discountAccountId'),
            writeOffAccountId: $rate->nullableUuid('writeOffAccountId'),
            translations: self::translations($rate),
            allocationRules: array_map(
                static fn (JsonValue $rule): VatAllocationRule => new VatAllocationRule(
                    id: $rule->uuid('ruleId'),
                    isVatLine: $rule->bool('isVatLine'),
                    percentage: Percentage::of($rule->decimal('percent')),
                    generalLedgerAccountId: $rule->nullableUuid('generalLedgerAccountId'),
                    costCenterId: $rule->nullableUuid('costCenterAccountId'),
                    vatGroupId: $rule->nullableUuid('vatGroupId'),
                ),
                $rate->list('allocationRules'),
            ),
            version: $rate->int('version'),
            createdAt: $rate->timestamp('createdAt'),
            lastModifiedAt: $rate->timestamp('lastModifiedAt'),
            modifiedBy: $rate->string('modifiedBy'),
        );
    }

    /**
     * @return list<VatCodeTranslation>
     */
    private static function translations(JsonValue $json): array
    {
        return array_map(
            static fn (JsonValue $translation): VatCodeTranslation => new VatCodeTranslation(
                language: $translation->string('language'),
                name: $translation->string('name'),
                shortName: $translation->nullableString('shortName'),
                invoiceText: $translation->nullableString('invoiceText'),
            ),
            $json->list('translations'),
        );
    }
}
