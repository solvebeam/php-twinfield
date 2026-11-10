<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * Maps the JSON of the fixed assets endpoints of the REST API onto the models.
 *
 * @internal
 */
final class FixedAssetReader
{
    public static function summary(JsonValue $json, CompanyReference $company): FixedAssetSummary
    {
        $balances = $json->object('youngestBalances');

        return new FixedAssetSummary(
            company: $company,
            id: $json->uuid('id'),
            code: $json->string('code'),
            description: $json->string('description'),
            status: $json->enum('status', FixedAssetStatus::class),
            postingErrorMessage: $json->nullableString('postingErrorMessage'),
            lastDepreciatedPeriod: self::nullablePeriod($json, 'lastDepreciatedPeriod'),
            investmentDate: $json->date('investmentDate'),
            firstUseDate: $json->date('firstUseDate'),
            disposeDate: $json->nullableDate('disposeDate'),
            disposePrice: $json->amount('disposePrice'),
            netBookValue: $balances->amount('netBookValue'),
            purchaseValue: $balances->amount('purchaseValue'),
            groupId: $json->nullableUuid('groupId'),
            version: $json->int('version'),
        );
    }

    public static function asset(JsonValue $json, CompanyReference $company): FixedAsset
    {
        $labels = $json->object('labels');
        $balances = $json->object('youngestBalances');
        $account = $json->object('purchaseValueAccount');

        $freeTexts = [];

        foreach (range(1, 5) as $number) {
            $text = $labels->nullableString('free' . $number);

            if ($text !== null) {
                $freeTexts[$number] = $text;
            }
        }

        $currentPeriodNumbers = [];

        foreach ($json->list('currentPeriodNumbers') as $current) {
            $currentPeriodNumbers[$current->enum('regime', Regime::class)->value] = $current->int('periodNumber');
        }

        $groups = [];

        foreach ($json->list('groups') as $group) {
            $groups[$group->uuid('id')] = $group->int('yearFrom');
        }

        return new FixedAsset(
            company: $company,
            id: $json->uuid('id'),
            code: $json->string('code'),
            description: $json->string('description'),
            status: $json->enum('status', FixedAssetStatus::class),
            currency: $json->string('currency'),
            supplierId: $json->nullableUuid('supplierId'),
            projectId: $json->nullableUuid('projectId'),
            transactionIds: array_map(self::id(...), $json->list('transactions')),
            nextPeriodToDepreciate: self::nullablePeriod($json, 'nextPeriodToDepreciate'),
            depreciationSettings: array_map(self::depreciationSettings(...), $json->list('depreciationSettings')),
            investmentDate: $labels->date('investmentDate'),
            firstUseDate: $labels->date('firstUseDate'),
            freeTexts: $freeTexts,
            licensePlate: $labels->nullableString('licensePlate'),
            serialNumber: $labels->nullableString('serialNumber'),
            taxReasonId: $labels->nullableUuid('taxReasonId'),
            taxReasonAmount: $labels->nullableAmount('taxReasonAmount'),
            locations: array_map(self::timeRelated(...), $labels->list('locations')),
            partners: array_map(
                static fn (JsonValue $partner): FixedAssetPartner
                    => new FixedAssetPartner($partner->uuid('id'), $partner->date('date')),
                $labels->list('partners'),
            ),
            assetTypes: array_map(
                static fn (JsonValue $types): FixedAssetTypes => new FixedAssetTypes(
                    year: $types->int('year'),
                    type1Id: $types->uuid('assetType1Id'),
                    type2Id: $types->nullableUuid('assetType2Id'),
                    type2Required: $types->bool('assetType2IsRequired'),
                    type3Id: $types->nullableUuid('assetType3Id'),
                    type3Required: $types->bool('assetType3IsRequired'),
                ),
                $labels->list('assetTypes'),
            ),
            attachmentIds: $json->strings('attachmentIds'),
            currentPeriodNumbers: $currentPeriodNumbers,
            purchaseValues: array_map(
                static fn (JsonValue $value): FixedAssetPurchaseValue => new FixedAssetPurchaseValue(
                    regime: $value->enum('regime', Regime::class),
                    amount: $value->amount('amount'),
                    fromPeriodNumber: $value->int('periodFrom'),
                    origin: $value->string('origin'),
                ),
                $json->list('purchaseValues'),
            ),
            classes: array_map(
                static fn (JsonValue $class): YearRelatedReference
                    => new YearRelatedReference($class->uuid('id'), $class->int('year')),
                $json->list('classes'),
            ),
            costCenters: array_map(self::timeRelated(...), $json->list('costCenters')),
            groups: $groups,
            isImportedWithMimicHistory: $json->bool('isImportedWithMimicHistory'),
            lastMimicPeriod: self::nullablePeriod($json, 'lastMimicPeriod'),
            lastRevaluationPeriod: self::nullablePeriod($json, 'lastRevaluationPeriod'),
            parents: array_map(self::link(...), $json->list('parentFixedAssets')),
            children: array_map(self::link(...), $json->list('childFixedAssets')),
            targetCompany: $json->nullableString('targetCompany'),
            sourceCompany: $json->nullableString('sourceCompany'),
            purchaseValueAccount: new AccountReference(
                $account->uuid('id'),
                $account->string('code'),
                $account->string('name'),
            ),
            netBookValue: $balances->amount('netBookValue'),
            purchaseValue: $balances->amount('purchaseValue'),
            version: $json->int('version'),
        );
    }

    public static function action(JsonValue $json): FixedAssetAction
    {
        return new FixedAssetAction(
            type: $json->enum('action', FixedAssetActionType::class),
            timestamp: $json->timestamp('timestamp'),
            user: $json->string('user'),
            transactions: array_map(
                static function (JsonValue $transaction): FixedAssetTransaction {
                    $number = $transaction->string('number');

                    return new FixedAssetTransaction(
                        $transaction->string('type'),
                        ctype_digit($number)
                            ? (int) $number
                            : throw $transaction->invalid('number', 'a transaction number'),
                    );
                },
                $json->list('transactions'),
            ),
        );
    }

    public static function classSummary(JsonValue $json, CompanyReference $company): FixedAssetClassSummary
    {
        return new FixedAssetClassSummary(
            company: $company,
            id: $json->uuid('id'),
            name: $json->string('name'),
            method: $json->enum('method', DepreciationMethod::class),
            lifetimeType: $json->enum('lifetimeType', LifetimeType::class),
            lifetime: $json->decimal('lifetime'),
            used: $json->bool('used'),
            version: $json->int('version'),
        );
    }

    public static function fixedAssetClass(JsonValue $json, CompanyReference $company): FixedAssetClass
    {
        return new FixedAssetClass(
            company: $company,
            id: $json->uuid('id'),
            code: $json->string('code'),
            name: $json->string('name'),
            activationStatus: $json->enum('activationStatus', ClassActivationStatus::class),
            used: $json->bool('used'),
            depreciationSettings: array_map(
                static fn (JsonValue $settings): ClassDepreciationSetting => new ClassDepreciationSetting(
                    regime: $settings->enum('regime', Regime::class),
                    method: $settings->enum('depreciationMethod', DepreciationMethod::class),
                    lifetimeType: $settings->enum('lifetimeType', LifetimeType::class),
                    lifetime: $settings->decimal('lifetime'),
                ),
                $json->list('depreciationSettings'),
            ),
            purchaseValueAccountId: $json->uuid('purchaseValueAccountId'),
            depreciationBalanceAccountId: $json->uuid('depreciationBalanceAccountId'),
            depreciationProfitAndLossAccountId: $json->uuid('depreciationProfitAndLossAccountId'),
            disposalBalanceAccountId: $json->uuid('disposalBalanceAccountId'),
            disposalResultAccountId: $json->uuid('disposalResultAccountId'),
            revaluationBalanceAccountId: $json->uuid('revaluationBalanceAccountId'),
            revaluationProfitAndLossAccountId: $json->uuid('revaluationProfitAndLossAccountId'),
            revaluationDepreciationBalanceAccountId: $json->uuid('revaluationDepreciationBalanceAccountId'),
            revaluationDepreciationProfitAndLossAccountId: $json->uuid('revaluationDepreciationProfitAndLossAccountId'),
            version: $json->int('version'),
        );
    }

    public static function groupSummary(JsonValue $json, CompanyReference $company): FixedAssetGroupSummary
    {
        return new FixedAssetGroupSummary($company, $json->uuid('id'), $json->string('name'));
    }

    public static function group(JsonValue $json, CompanyReference $company): FixedAssetGroup
    {
        $stopValues = [];

        foreach ($json->list('stopValues') as $stopValue) {
            foreach ($stopValue->object('valuesPerRegime')->list('values') as $value) {
                $regime = $value->enum('regime', Regime::class)->value;
                $stopValues[$stopValue->int('year')][$regime] = $value->amount('value');
            }
        }

        return new FixedAssetGroup(
            company: $company,
            id: $json->uuid('id'),
            name: $json->string('name'),
            startYear: $json->int('startYear'),
            stopValues: $stopValues,
            version: $json->int('version'),
        );
    }

    private static function depreciationSettings(JsonValue $json): DepreciationSettings
    {
        return new DepreciationSettings(
            regime: $json->enum('regime', Regime::class),
            startPeriod: self::period($json->object('startYearPeriod')),
            startDate: $json->date('startDate'),
            remainderValue: $json->amount('remainderValue'),
            isRemainderValueImplicit: $json->nullableBool('isRemainderValueImplicit'),
            stopValue: $json->amount('stopValue'),
            methods: array_map(
                static fn (JsonValue $method): DepreciationMethodSetting => new DepreciationMethodSetting(
                    method: $method->enum('depreciationMethod', DepreciationMethod::class),
                    lifetimeType: $method->enum('lifetimeType', LifetimeType::class),
                    lifetime: $method->decimal('lifetime'),
                    from: self::period($method->object('period')),
                ),
                $json->list('methods'),
            ),
        );
    }

    private static function id(JsonValue $json): string
    {
        return $json->uuid('id');
    }

    /**
     * `{"id": "…", "year": 2021, "periodNumber": 12}`.
     */
    private static function timeRelated(JsonValue $json): TimeRelatedReference
    {
        return new TimeRelatedReference($json->uuid('id'), self::period($json));
    }

    private static function link(JsonValue $json): FixedAssetLink
    {
        return new FixedAssetLink(
            $json->uuid('id'),
            $json->string('assetDescription'),
            $json->enum('fixedAssetAction', FixedAssetRelation::class),
        );
    }

    /**
     * `{"year": 2021, "periodNumber": 4}`.
     */
    private static function period(JsonValue $json): Period
    {
        return Period::of($json->int('year'), $json->int('periodNumber'));
    }

    private static function nullablePeriod(JsonValue $json, string $name): ?Period
    {
        $period = $json->nullableObject($name);

        return $period === null ? null : self::period($period);
    }
}
