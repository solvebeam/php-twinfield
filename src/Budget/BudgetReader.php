<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Rest\JsonValue;

/**
 * Maps the JSON of the budgeting endpoints of the REST API onto the models.
 *
 * @internal
 */
final class BudgetReader
{
    public static function budget(JsonValue $json, CompanyReference $company): Budget
    {
        return new Budget(
            company: $company,
            id: $json->uuid('id'),
            code: $json->string('code'),
            name: $json->string('name'),
            description: $json->nullableString('description'),
            useSystemPeriods: $json->bool('useSystemPeriods'),
            createdAt: $json->timestamp('createdAt'),
            lastModifiedAt: $json->timestamp('lastModifiedAt'),
        );
    }

    public static function line(JsonValue $json): BudgetLine
    {
        return new BudgetLine(
            budgetId: $json->uuid('budget'),
            key: new BudgetLineKey(
                Period::of($json->int('year'), $json->int('period')),
                $json->enum('unitOfMeasure', UnitOfMeasure::class),
                $json->uuid('generalLedgerAccount'),
                $json->nullableUuid('costCenter'),
                $json->nullableUuid('project'),
            ),
            debit: $json->decimal('debit'),
            credit: $json->decimal('credit'),
            createdAt: $json->timestamp('createdAt'),
            lastModifiedAt: $json->timestamp('lastModifiedAt'),
        );
    }

    public static function period(JsonValue $json): BudgetPeriod
    {
        return new BudgetPeriod(Period::of($json->int('year'), $json->int('periodNumber')), $json->bool('isSystem'));
    }
}
