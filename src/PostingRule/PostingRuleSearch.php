<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PostingRule;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * Posting rules changed since a moment, created by `postingRules()->modifiedSince($since)`.
 *
 * @extends RestSearch<PostingRule>
 */
final readonly class PostingRuleSearch extends RestSearch
{
    protected function map(JsonValue $item, CompanyReference $company): PostingRule
    {
        return new PostingRule(
            company: $company,
            id: $item->uuid('id'),
            type: $item->enum('type', PostingRuleType::class),
            accountId: $item->uuid('accountId'),
            currencyId: $item->nullableUuid('currencyId'),
            amount: $item->nullableAmount('amount'),
            description: $item->nullableString('description'),
            lines: array_map(
                static fn (JsonValue $line): PostingRuleLine => new PostingRuleLine(
                    generalLedgerAccountId: $line->nullableUuid('generalLedgerAccount'),
                    destinationCompanyId: $line->nullableUuid('destinationCompany'),
                    costCenterId: $line->nullableUuid('costCenter'),
                    projectId: $line->nullableUuid('project'),
                    vatCodeId: $line->nullableUuid('vatCodeId'),
                    ratio: $line->nullableDecimal('ratio'),
                    description: $line->nullableString('description'),
                ),
                $item->list('lines'),
            ),
            version: $item->int('version'),
            created: $item->timestamp('created'),
            modified: $item->timestamp('modified'),
            modifiedBy: $item->string('modifiedBy'),
        );
    }
}
